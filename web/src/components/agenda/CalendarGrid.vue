<script setup lang="ts">
import type { CalendarApi, DateSelectArg, EventClickArg, EventInput } from '@fullcalendar/core'
import frLocale from '@fullcalendar/core/locales/fr'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import timeGridPlugin from '@fullcalendar/timegrid'
import FullCalendar from '@fullcalendar/vue3'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CalEvent, CalendarSource, SlotHold } from '../../domain/types'
import BaseIcon from '../ui/BaseIcon.vue'
import { overlapping } from '../../domain/holds'

/**
 * Grille de calendrier : FullCalendar (MIT, maintenu), habillé avec nos jetons de thème.
 * La barre d'outils est la nôtre (boutons accessibles, annonces live) ; la grille ne fait que dessiner et réagir.
 * La source d'un événement est toujours dite par un libellé, jamais par la seule couleur.
 */
export type GridView = 'timeGridWeek' | 'timeGridDay' | 'dayGridMonth' | 'listWeek'

const props = defineProps<{ events: CalEvent[]; sources: CalendarSource[]; view: GridView; conflicts: Set<string>; busy?: { member: string; start: string; end: string }[]; holds?: SlotHold[] }>()
const emit = defineEmits<{
  open: [id: string]
  hold: [groupId: string]
  select: [range: { start: Date; end: Date }]
  dates: [info: { start: Date; end: Date; title: string; current: Date }]
}>()
const { t, locale } = useI18n()
const cal = ref<InstanceType<typeof FullCalendar> | null>(null)
const api = (): CalendarApi | undefined => cal.value?.getApi()

const sourceOf = (id: string) => props.sources.find((s) => s.id === id)
const items = computed<EventInput[]>(() => [
  ...(props.busy ?? []).map((b, i) => ({
    id: `busy-${i}`, title: t('agenda.busyOf', { who: b.member }), start: b.start, end: b.end, editable: false, classNames: ['ev-busy'],
    extendedProps: { location: null, source: t('agenda.team'), personal: false, conflict: false, busy: true },
  })),
  // Créneaux en provisoire (F-74) : en pointillés, avec leur libellé et leur icône.
  ...(props.holds ?? []).map((h) => ({
    id: `hold-${h.id}`, title: t('holds.proposedTo', { who: h.contact }), start: h.start, end: h.end, editable: false, classNames: ['ev-hold'],
    extendedProps: { location: null, source: t('holds.chip'), personal: false, conflict: props.events.some((e) => !sourceOf(e.calendar)?.personal && overlapping(e, h)), hold: h.groupId },
  })),
  ...props.events.map((e) => ({
    id: e.id, title: e.title, start: e.start, end: e.end,
    classNames: [sourceOf(e.calendar)?.personal ? 'ev-personal' : 'ev-work', ...(props.conflicts.has(e.id) ? ['ev-conflict'] : [])],
    extendedProps: { location: e.location, source: sourceOf(e.calendar)?.label ?? '', personal: !!sourceOf(e.calendar)?.personal, conflict: props.conflicts.has(e.id) },
  })),
])

const options = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  initialView: props.view,
  locales: [frLocale],
  locale: locale.value,
  firstDay: 1,
  headerToolbar: false as const,
  height: 'min(74vh, 780px)',
  allDaySlot: false,
  nowIndicator: true,
  scrollTime: '07:30:00',
  slotMinTime: '06:00:00',
  slotMaxTime: '21:00:00',
  slotDuration: '00:30:00',
  slotLabelFormat: { hour: '2-digit' as const, minute: '2-digit' as const, hour12: false },
  eventTimeFormat: { hour: '2-digit' as const, minute: '2-digit' as const, hour12: false },
  expandRows: true,
  dayMaxEvents: 3,
  slotEventOverlap: false, // événements simultanés côte à côte, jamais l'un sur l'autre
  selectable: true,
  selectMirror: true,
  eventInteractive: true, // chaque événement devient focalisable au clavier (Entrée pour ouvrir)
  businessHours: { daysOfWeek: [1, 2, 3, 4, 5], startTime: '08:00', endTime: '18:00' },
  events: items.value,
  select: (a: DateSelectArg) => { emit('select', { start: a.start, end: a.end }); api()?.unselect() },
  eventClick: (a: EventClickArg) => {
    if (a.event.extendedProps.hold) emit('hold', a.event.extendedProps.hold as string)
    else if (!a.event.id.startsWith('busy-')) emit('open', a.event.id)
  },
  datesSet: (a: { start: Date; end: Date; view: { title: string; currentStart: Date } }) =>
    emit('dates', { start: a.start, end: a.end, title: a.view.title, current: a.view.currentStart }),
}))

defineExpose({
  changeView: (v: GridView) => api()?.changeView(v),
  prev: () => api()?.prev(),
  next: () => api()?.next(),
  today: () => api()?.today(),
})
</script>

<template>
  <div class="grid-wrap">
    <FullCalendar ref="cal" :options="options">
      <template #eventContent="arg">
        <div class="ev">
          <span v-if="arg.timeText" class="ev-time">{{ arg.timeText }}</span>
          <span class="ev-title">{{ arg.event.title }}</span>
          <span v-if="arg.event.extendedProps.location && arg.view.type !== 'dayGridMonth'" class="ev-meta">{{ arg.event.extendedProps.location }}</span>
          <span class="ev-meta ev-source">
            <BaseIcon :name="arg.event.extendedProps.hold ? 'clock' : arg.event.extendedProps.personal ? 'user' : 'calendar'" :size="12" />{{ arg.event.extendedProps.source }}
          </span>
          <span v-if="arg.event.extendedProps.conflict" class="ev-meta ev-warn" :title="t('agenda.conflict')"><BaseIcon name="alert" :size="12" /><span class="ev-warn-txt">{{ t('agenda.conflict') }}</span></span>
        </div>
      </template>
    </FullCalendar>
  </div>
</template>

<style>
/* Habillage de FullCalendar par nos jetons : thèmes clair, sombre et contraste élevé sans toucher au composant. */
.grid-wrap .fc {
  --fc-page-bg-color: var(--c-surface);
  --fc-neutral-bg-color: var(--c-surface-2);
  --fc-neutral-text-color: var(--c-text-muted);
  --fc-border-color: var(--c-border);
  --fc-today-bg-color: var(--c-primary-soft);
  --fc-now-indicator-color: var(--c-danger);
  --fc-non-business-color: color-mix(in srgb, var(--c-surface-2) 55%, transparent);
  --fc-event-bg-color: var(--c-primary-soft);
  --fc-event-border-color: var(--c-primary);
  --fc-event-text-color: var(--c-on-primary-soft);
  --fc-list-event-hover-bg-color: var(--c-surface-2);
  --fc-highlight-color: var(--c-primary-soft);
  --fc-more-link-bg-color: var(--c-surface-2);
  font-family: var(--font-sans);
  color: var(--c-text);
  background: var(--c-surface);
  border-radius: var(--radius-md);
}
.grid-wrap .fc .fc-scrollgrid { border-radius: var(--radius-md); overflow: hidden; }
.grid-wrap .fc a { color: inherit; text-decoration: none; }
.grid-wrap .fc .fc-col-header-cell-cushion { padding: var(--space-2); font-weight: 700; text-transform: capitalize; min-height: var(--target); display: inline-flex; align-items: center; }
.grid-wrap .fc .fc-timegrid-slot-label-cushion, .grid-wrap .fc .fc-timegrid-axis-cushion { font-size: var(--fs-sm); color: var(--c-text-muted); }
.grid-wrap .fc .fc-timegrid-slot { height: 28px; }
.grid-wrap .fc .fc-list-day-cushion { background: var(--c-surface-2); text-transform: capitalize; }
.grid-wrap .fc .fc-list-event td { padding: var(--space-3); }
.grid-wrap .fc .fc-list-empty { background: var(--c-surface); color: var(--c-text-muted); }

/* Événements : agenda pro en bleu, agenda perso en sarcelle ; libellé de source toujours présent (jamais la couleur seule). */
.grid-wrap .fc-event { border-radius: var(--radius-sm); border-width: 0 0 0 4px; box-shadow: var(--shadow-1); cursor: pointer; }
.grid-wrap .fc-event.ev-personal {
  --fc-event-bg-color: var(--c-cal2-soft); --fc-event-border-color: var(--c-cal2); --fc-event-text-color: var(--c-on-cal2-soft);
}
.grid-wrap .fc-event.ev-busy { background: repeating-linear-gradient(135deg, var(--c-surface-2) 0 6px, var(--c-surface) 6px 12px); border: 1px dashed var(--c-border-strong); color: var(--c-text-muted); }
.grid-wrap .fc-event.ev-hold { background: var(--c-surface); border: 2px dashed var(--c-primary); box-shadow: none; --fc-event-text-color: var(--c-text); color: var(--c-text); }
.grid-wrap .fc-event.ev-conflict { outline: 2px dashed var(--c-warn); outline-offset: -2px; }
.grid-wrap .fc-event:focus-visible { outline: 3px solid var(--c-focus); outline-offset: 2px; z-index: 5; }
.grid-wrap .ev { display: flex; flex-direction: column; gap: 1px; padding: 2px 4px; overflow: hidden; line-height: 1.25; font-size: var(--fs-sm); }
.grid-wrap .ev-time { font-weight: 700; font-size: 0.75rem; }
.grid-wrap .ev-title { font-weight: 800; overflow-wrap: break-word; hyphens: manual; }
.grid-wrap .fc-event { container-type: inline-size; }
.grid-wrap .fc-timegrid-event { container-type: size; overflow: hidden; }
.grid-wrap .fc-timegrid-event .fc-event-main { height: 100%; overflow: hidden; }
.grid-wrap .fc-timegrid-event .ev { position: relative; height: 100%; }
.grid-wrap .fc-timegrid-event .ev-warn { position: absolute; bottom: 2px; right: 2px; padding: 0 4px; }
@container (max-height: 90px) { .ev-meta:not(.ev-warn) { display: none !important; } }
@container (max-width: 110px) { .ev-meta:not(.ev-warn), .ev-time { display: none !important; } .ev-warn-txt { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); } .ev-title { font-size: 0.75rem; } }
.grid-wrap .fc-timegrid-event-short .ev-meta, .grid-wrap .fc-timegrid-col-events .fc-timegrid-event.fc-event-end .ev-source { display: none; }
.grid-wrap .fc-timegrid-event .ev-warn { display: inline-flex; background: var(--c-warn-soft); color: var(--c-text); border-radius: 999px; padding: 0 6px; width: fit-content; }
.grid-wrap .fc-timegrid-event-short .ev-warn { display: inline-flex; }
.grid-wrap .ev-meta { font-size: 0.75rem; display: inline-flex; align-items: center; gap: 3px; opacity: 0.95; }
.grid-wrap .ev-warn { color: var(--c-warn); font-weight: 700; }
:root[data-theme='contrast'] .grid-wrap .fc-event { border: 2px solid currentColor; }
.grid-wrap .fc-list-event .ev { flex-direction: row; flex-wrap: wrap; gap: var(--space-3); align-items: center; }
.grid-wrap .fc-list-event-graphic { display: none; }
.grid-wrap .fc .fc-list-event-time { white-space: nowrap; font-weight: 700; }
@media (max-width: 720px) { .grid-wrap .fc .fc-timegrid-slot-label-cushion { font-size: 0.7rem; } }
</style>
