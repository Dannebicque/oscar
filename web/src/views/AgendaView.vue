<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api'
import CalendarGrid, { type GridView } from '../components/agenda/CalendarGrid.vue'
import HoldsPanel from '../components/agenda/HoldsPanel.vue'
import MeetingBrief from '../components/agenda/MeetingBrief.vue'
import Callout from '../components/ui/Callout.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseDialog from '../components/ui/BaseDialog.vue'
import BaseIcon from '../components/ui/BaseIcon.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import SelectField from '../components/ui/SelectField.vue'
import TextField from '../components/ui/TextField.vue'
import ToneChip from '../components/ui/ToneChip.vue'
import { useFormat } from '../composables/useFormat'
import { conflictIds } from '../domain/agenda'
import { overlapping } from '../domain/holds'
import { useMailsStore } from '../stores/mails'
import { isoDay } from '../domain/dates'
import { useAgendaStore } from '../stores/agenda'
import { useSuivisStore } from '../stores/suivis'
import { useAccountsStore } from '../stores/accounts'
import { useTasksStore } from '../stores/tasks'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const fmt = useFormat()
const store = useAgendaStore()
const ui = useUiStore()
const tasks = useTasksStore()
const suivis = useSuivisStore()
const accounts = useAccountsStore()
const mails = useMailsStore()
onMounted(() => Promise.all([store.load(), tasks.load(), suivis.load(), mails.items.length ? Promise.resolve() : mails.load(), accounts.loaded ? Promise.resolve() : accounts.load().catch(() => undefined)]))

const narrow = typeof matchMedia === 'function' && matchMedia('(max-width: 720px)').matches
const view = ref<GridView>(narrow ? 'timeGridDay' : 'timeGridWeek')
const grid = ref<InstanceType<typeof CalendarGrid> | null>(null)
const title = ref('')
const range = ref<{ start: Date; end: Date } | null>(null)
/** Calendriers masqués (bascule par source) : la grille n'affiche que ceux qui restent actifs. */
const hidden = ref<Set<string>>(new Set())
/** Disponibilités de l'équipe (F-26) : créneaux occupés des collègues qui partagent leur agenda pro, sans aucun détail. */
const teamOn = ref(false)
const teamBusy = ref<{ member: string; start: string; end: string }[]>([])
const box = computed(() => accounts.shared[0]?.slug ?? '')
/** FullCalendar annonce la période à chaque rendu : on ne réagit que si elle change vraiment (sinon boucle de rendu). */
function setRange(d: { start: Date; end: Date; title: string }) {
  title.value = d.title
  if (range.value?.start.getTime() !== d.start.getTime() || range.value?.end.getTime() !== d.end.getTime()) range.value = { start: d.start, end: d.end }
}
async function loadTeam() {
  if (!teamOn.value || !box.value || !range.value) { if (teamBusy.value.length) teamBusy.value = []; return }
  teamBusy.value = await api.agenda.teamBusy(box.value, range.value.start, range.value.end).catch(() => [])
}
watch([teamOn, range], loadTeam)
const shownEvents = computed(() => store.events.filter((e) => !hidden.value.has(e.calendar)))
function toggleSource(id: string) {
  const next = new Set(hidden.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  hidden.value = next
}
/** Échéances de la période affichée : tâches non terminées et suivis ouverts dont l'échéance tombe dans la période. */
const dues = computed(() => {
  if (!range.value) return []
  const from = isoDay(range.value.start)
  const to = isoDay(new Date(range.value.end.getTime() - 1))
  const rows = [
    ...tasks.items.filter((x) => !x.done && !x.toConfirm && x.dueAt && x.dueAt >= from && x.dueAt <= to).map((x) => ({ key: `t${x.id}`, label: x.title, tag: t('agenda.dueTask'), at: x.dueAt!, to: '/taches' })),
    ...suivis.items.filter((x) => x.status !== 'done' && x.dueAt >= from && x.dueAt <= to).map((x) => ({ key: `s${x.id}`, label: x.subject, tag: t('agenda.dueSuivi'), at: x.dueAt, to: '/suivis' })),
  ]
  return rows.sort((a, b) => a.at.localeCompare(b.at)).slice(0, 8)
})
const viewOptions = computed(() => ([['timeGridWeek', 'week'], ['timeGridDay', 'day'], ['dayGridMonth', 'month'], ['listWeek', 'list']] as const).map(([value, k]) => ({ value: value as GridView, label: t(`agenda.${k}`) })))
const filtersOpen = ref(false)
const conflicts = computed(() => conflictIds(store.events))

/** Proposition désignée en cliquant un créneau en pointillés dans la grille. */
const holdHit = ref<string | null>(null)
function showHold(groupId: string) {
  holdHit.value = groupId
  document.querySelector(`[data-group="${groupId}"]`)?.scrollIntoView?.({ block: 'nearest' })
}

function setView(v: GridView) {
  view.value = v
  grid.value?.changeView(v)
}

/** Détail d'un événement (lecture seule : l'API ne modifie pas encore les événements existants). */
const openId = ref<string | null>(null)
const opened = computed(() => store.events.find((e) => e.id === openId.value) ?? null)
const openedSource = computed(() => (opened.value ? store.sourceOf(opened.value.calendar) : undefined))

/** Un événement peut générer une tâche de préparation (F-23) : un clic, jamais automatique ; échéance la veille (jour ouvré précédent). */
const prepDone = ref<Set<string>>(new Set())
async function addPrep() {
  const e = opened.value
  if (!e) return
  const d = new Date(e.start)
  do d.setDate(d.getDate() - 1); while (d.getDay() === 0 || d.getDay() === 6)
  await tasks.add({ title: t('agenda.prepTitle', { title: e.title }), dueAt: isoDay(d), origin: 'event', source: e.title })
  prepDone.value = new Set([...prepDone.value, e.id])
  ui.notify(t('agenda.prepAdded', { title: e.title }))
}
const showNew = ref(false)
const form = ref({ title: '', day: isoDay(new Date()), start: '09:00', end: '10:00', location: '', calendar: '' })
const hhmm = (d: Date) => `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
/** Un glisser sur la grille préremplit le jour et les heures ; le bouton « Nouvel événement » reste le chemin clavier. */
function startCreate(range?: { start: Date; end: Date }) {
  const first = store.sources.find((s) => !s.personal) ?? store.sources[0]
  form.value = {
    title: '', day: isoDay(range?.start ?? new Date()), start: range ? hhmm(range.start) : '09:00',
    end: range && range.end.getTime() - range.start.getTime() >= 15 * 60_000 ? hhmm(range.end) : '10:00', location: '', calendar: first?.id ?? '',
  }
  showNew.value = true
}
/** Le nouvel événement tombe sur un créneau proposé à quelqu'un : on le dit avant de créer. */
const formClash = computed(() => {
  const f = form.value
  const start = new Date(`${f.day}T${f.start}`), end = new Date(`${f.day}T${f.end}`)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null
  return store.holds.find((h) => overlapping(h, { start: start.toISOString(), end: end.toISOString() })) ?? null
})
async function create() {
  if (!form.value.title.trim() || !form.value.calendar) return
  const [y, mo, d] = form.value.day.split('-').map(Number)
  const at = (hm: string) => { const [h, m] = hm.split(':').map(Number); return new Date(y, mo - 1, d, h, m).toISOString() }
  try {
    await store.add({ calendar: form.value.calendar, title: form.value.title.trim(), start: at(form.value.start), end: at(form.value.end), location: form.value.location.trim() || null })
    ui.notify(t('agenda.created', { title: form.value.title }))
    showNew.value = false
  } catch {
    ui.notify(t('agenda.createFailed'), { tone: 'error' })
  }
}
</script>

<template>
  <PageHeader :title="t('agenda.title')">
    <BaseButton variant="primary" icon="plus" :label="t('agenda.add')" @click="startCreate()" />
  </PageHeader>
  <ErrorState v-if="store.error" @retry="store.load()" />
  <template v-else>
    <div class="bar">
      <SegmentedControl :model-value="view" :options="viewOptions" :label="t('agenda.views')" @update:model-value="setView" />
      <div class="nav">
        <BaseButton icon="chevronLeft" icon-only :label="t('agenda.prev')" @click="grid?.prev()" />
        <BaseButton :label="t('agenda.todayBtn')" @click="grid?.today()" />
        <BaseButton icon="chevronRight" icon-only :label="t('agenda.next')" @click="grid?.next()" />
      </div>
      <h2 class="when" aria-live="polite">{{ title }}</h2>
    </div>
    <div class="layout">
      <div class="main">
        <button type="button" class="src-toggle btn" :aria-expanded="filtersOpen" aria-controls="agenda-sources" @click="filtersOpen = !filtersOpen">
          <BaseIcon name="calendar" :size="16" />{{ t('agenda.filters', { shown: store.sources.length - hidden.size, total: store.sources.length }) }}
        </button>
        <ul id="agenda-sources" class="sources" :class="{ open: filtersOpen }" :aria-label="t('agenda.sources')">
          <li v-for="s in store.sources" :key="s.id">
            <button type="button" class="src" :class="{ off: hidden.has(s.id), personal: s.personal }" :aria-pressed="!hidden.has(s.id)" @click="toggleSource(s.id)">
              <span class="bar" aria-hidden="true" />
              <BaseIcon :name="s.personal ? 'user' : 'calendar'" :size="16" />
              <span>{{ s.label }}<template v-if="s.personal"> · {{ t('agenda.private') }}</template></span>
            </button>
          </li>
          <li v-if="box">
            <button type="button" class="src team" :class="{ off: !teamOn }" :aria-pressed="teamOn" @click="teamOn = !teamOn"><span class="bar" aria-hidden="true" /><BaseIcon name="users" :size="16" /><span>{{ t('agenda.teamToggle') }}</span></button>
          </li>
        </ul>
        <Callout v-if="!store.loading && store.sources.length === 0" class="nosource" :level="2" tone="warn" icon="calendar" :title="t('agenda.noSource')">
          <p>{{ t('agenda.noSourceHelp') }}</p>
          <template #actions><RouterLink class="btn primary" to="/reglages#set-accounts">{{ t('agenda.addSource') }}</RouterLink></template>
        </Callout>
        <p v-if="store.loading" class="muted">{{ t('common.loading') }}</p>
        <CalendarGrid
          ref="grid" :events="shownEvents" :busy="teamBusy" :sources="store.sources" :view="view" :conflicts="conflicts" :holds="store.holds"
          @hold="showHold" @open="openId = $event" @select="startCreate" @dates="setRange"
        />
        <p class="hint note">{{ t('agenda.legend') }} {{ t('agenda.freeBusy') }}</p>
      </div>

      <aside class="side" :aria-label="t('agenda.sidePanel')">
        <section class="card stack" aria-labelledby="sel-h">
          <h2 id="sel-h">{{ t('agenda.selected') }}</h2>
          <p v-if="!opened" class="muted">{{ t('agenda.selectHint') }}</p>
          <template v-else>
            <p class="evt">{{ opened.title }}</p>
            <dl class="detail">
              <dt>{{ t('agenda.when') }}</dt>
              <dd><BaseIcon name="clock" :size="14" /> {{ fmt.dateLong(opened.start) }}, {{ fmt.time(opened.start) }} – {{ fmt.time(opened.end) }}</dd>
              <template v-if="opened.location"><dt>{{ t('agenda.evPlace') }}</dt><dd><BaseIcon name="mapPin" :size="14" /> {{ opened.location }}</dd></template>
              <dt>{{ t('agenda.evCalendar') }}</dt>
              <dd><ToneChip :tone="openedSource?.personal ? 'neutral' : 'info'" :icon="openedSource?.personal ? 'user' : 'calendar'" :label="openedSource?.label ?? ''" /></dd>
            </dl>
            <p v-if="conflicts.has(opened.id)" class="conflict"><ToneChip tone="warn" icon="alert" :label="t('agenda.conflict')" /></p>
            <MeetingBrief :event="opened" />
            <div class="evacts">
              <BaseButton icon="task" :disabled="prepDone.has(opened.id)" :label="prepDone.has(opened.id) ? t('agenda.prepDone') : t('agenda.prepTask')" @click="addPrep" />
              <BaseButton icon="x" :label="t('common.close')" @click="openId = null" />
            </div>
          </template>
        </section>

        <section v-if="store.holds.length" class="card stack" aria-labelledby="hold-h">
          <h2 id="hold-h">{{ t('holds.title') }}</h2>
          <HoldsPanel :holds="store.holds" :highlight="holdHit" />
        </section>

        <section class="card stack" aria-labelledby="due-h">
          <h2 id="due-h">{{ t('agenda.dues') }}</h2>
          <p v-if="dues.length === 0" class="muted">{{ t('agenda.noDues') }}</p>
          <ul v-else class="dues">
            <li v-for="d in dues" :key="d.key"><RouterLink :to="d.to" class="due"><span class="dl">{{ d.label }}</span><span class="dt muted">{{ d.tag }} · {{ fmt.date(d.at) }}</span></RouterLink></li>
          </ul>
        </section>
      </aside>
    </div>
  </template>

  <BaseDialog :open="showNew" :title="t('agenda.add')" @close="showNew = false">
    <form id="new-event" class="form" @submit.prevent="create">
      <TextField id="ev-title" v-model="form.title" :label="t('agenda.evTitle')" required />
      <TextField id="ev-day" v-model="form.day" type="date" :label="t('agenda.evDay')" />
      <div class="two">
        <TextField id="ev-s" v-model="form.start" type="time" :label="t('agenda.evStart')" />
        <TextField id="ev-e" v-model="form.end" type="time" :label="t('agenda.evEnd')" />
      </div>
      <p v-if="formClash" class="callout" data-tone="warn" role="status">{{ t('holds.clashHold', { who: formClash.contact }) }}</p>
      <TextField id="ev-p" v-model="form.location" :label="t('agenda.evPlace')" />
      <SelectField id="ev-c" v-model="form.calendar" :label="t('agenda.evCalendar')" :options="store.sources.map((s) => ({ value: s.id, label: s.label }))" required />
    </form>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="showNew = false" />
      <BaseButton variant="primary" type="submit" form="new-event" :label="t('common.add')" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.bar { display: flex; gap: var(--space-4); align-items: center; flex-wrap: wrap; margin-bottom: var(--space-3); }
.nav { display: flex; gap: var(--space-1); }
.when { font-size: var(--fs-md); flex: 1; min-width: 200px; }
.when::first-letter { text-transform: uppercase; }
.layout { display: grid; gap: var(--space-4); align-items: start; }
.main { min-width: 0; }
.nosource { margin-bottom: var(--space-4); }
.side { display: grid; gap: var(--space-4); align-content: start; min-width: 0; }
@media (min-width: 1200px) { .layout { grid-template-columns: minmax(0, 1fr) 320px; } .side { position: sticky; top: 0; } }
.sources { list-style: none; margin: 0 0 var(--space-3); padding: 0; display: flex; gap: var(--space-2); flex-wrap: wrap; }
.src { display: inline-flex; align-items: center; gap: var(--space-2); min-height: var(--target); padding: 0 var(--space-3); max-width: 100%; font-size: var(--fs-sm); border-radius: var(--radius-pill); border: var(--bw) solid var(--c-border-strong); background: var(--c-primary-soft); color: var(--c-on-primary-soft); font: inherit; font-weight: 700; cursor: pointer; }
.src.personal { background: var(--c-surface-2); color: var(--c-text); }
.src .bar { width: 4px; height: 18px; border-radius: 2px; background: var(--c-cal2, var(--c-primary)); }
.src:not(.personal) .bar { background: var(--c-primary); }
.src.off { background: var(--c-surface); color: var(--c-text-muted); text-decoration: line-through; }
.src.off .bar { background: var(--c-border-strong); }
.src-toggle { display: none; margin-bottom: var(--space-3); }
@media (max-width: 720px) { .src-toggle { display: inline-flex; } .sources:not(.open) { display: none; } }
.evacts { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.evt { font-size: var(--fs-lg); font-weight: 800; margin: 0; }
.dues { list-style: none; margin: 0; padding: 0; display: grid; }
.due { display: grid; gap: 2px; min-height: var(--target); padding: var(--space-2) 0; border-top: var(--bw) solid var(--c-border); color: var(--c-text); text-decoration: none; }
.due:hover .dl { text-decoration: underline; }
.dl { font-weight: 600; } .dt { font-size: var(--fs-sm); }
.side h2 { font-size: var(--fs-md); }
.note { margin-top: var(--space-4); }
.detail { display: grid; grid-template-columns: max-content 1fr; gap: var(--space-2) var(--space-4); margin: 0; }
.detail dt { color: var(--c-text-muted); }
.detail dd { margin: 0; display: flex; gap: var(--space-2); align-items: center; }
.form { display: grid; gap: var(--space-3); }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }
</style>
