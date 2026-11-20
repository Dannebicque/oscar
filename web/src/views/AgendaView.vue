<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import CalendarGrid, { type GridView } from '../components/agenda/CalendarGrid.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseDialog from '../components/ui/BaseDialog.vue'
import BaseIcon from '../components/ui/BaseIcon.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import ToneChip from '../components/ui/ToneChip.vue'
import { useFormat } from '../composables/useFormat'
import { conflictIds } from '../domain/agenda'
import { isoDay } from '../domain/dates'
import { useAgendaStore } from '../stores/agenda'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const fmt = useFormat()
const store = useAgendaStore()
const ui = useUiStore()
onMounted(() => store.load())

const narrow = typeof matchMedia === 'function' && matchMedia('(max-width: 720px)').matches
const view = ref<GridView>(narrow ? 'timeGridDay' : 'timeGridWeek')
const grid = ref<InstanceType<typeof CalendarGrid> | null>(null)
const title = ref('')
const viewOptions = computed(() => ([['timeGridWeek', 'week'], ['timeGridDay', 'day'], ['dayGridMonth', 'month'], ['listWeek', 'list']] as const).map(([value, k]) => ({ value: value as GridView, label: t(`agenda.${k}`) })))
const conflicts = computed(() => conflictIds(store.events))

function setView(v: GridView) {
  view.value = v
  grid.value?.changeView(v)
}

/** Détail d'un événement (lecture seule : l'API ne modifie pas encore les événements existants). */
const openId = ref<string | null>(null)
const opened = computed(() => store.events.find((e) => e.id === openId.value) ?? null)
const openedSource = computed(() => (opened.value ? store.sourceOf(opened.value.calendar) : undefined))

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
    <ul class="sources" :aria-label="t('agenda.sources')">
      <li v-for="s in store.sources" :key="s.id"><ToneChip :tone="s.personal ? 'neutral' : 'info'" :icon="s.personal ? 'user' : 'calendar'" :label="`${s.label} · ${s.kind === 'google' ? 'Google' : 'CalDAV'}${s.personal ? ' · ' + t('agenda.private') : ''}`" /></li>
    </ul>
    <p v-if="store.loading" class="muted">{{ t('common.loading') }}</p>
    <CalendarGrid
      ref="grid" :events="store.events" :sources="store.sources" :view="view" :conflicts="conflicts"
      @open="openId = $event" @select="startCreate" @dates="title = $event.title"
    />
    <p class="hint note">{{ t('agenda.freeBusy') }}</p>
  </template>

  <BaseDialog :open="opened !== null" :title="opened?.title ?? ''" @close="openId = null">
    <dl v-if="opened" class="detail">
      <dt>{{ t('agenda.when') }}</dt>
      <dd><BaseIcon name="clock" :size="14" /> {{ fmt.dateLong(opened.start) }}, {{ fmt.time(opened.start) }} – {{ fmt.time(opened.end) }}</dd>
      <template v-if="opened.location"><dt>{{ t('agenda.evPlace') }}</dt><dd><BaseIcon name="mapPin" :size="14" /> {{ opened.location }}</dd></template>
      <dt>{{ t('agenda.evCalendar') }}</dt>
      <dd><ToneChip :tone="openedSource?.personal ? 'neutral' : 'info'" :icon="openedSource?.personal ? 'user' : 'calendar'" :label="openedSource?.label ?? ''" /></dd>
    </dl>
    <p v-if="opened && conflicts.has(opened.id)" class="conflict"><ToneChip tone="warn" icon="alert" :label="t('agenda.conflict')" /></p>
    <template #footer><BaseButton :label="t('common.close')" @click="openId = null" /></template>
  </BaseDialog>

  <BaseDialog :open="showNew" :title="t('agenda.add')" @close="showNew = false">
    <form id="new-event" class="form" @submit.prevent="create">
      <div class="field"><label for="ev-title">{{ t('agenda.evTitle') }}</label><input id="ev-title" v-model="form.title" class="input" required autocomplete="off" /></div>
      <div class="field"><label for="ev-day">{{ t('agenda.evDay') }}</label><input id="ev-day" v-model="form.day" type="date" class="input" /></div>
      <div class="two">
        <div class="field"><label for="ev-s">{{ t('agenda.evStart') }}</label><input id="ev-s" v-model="form.start" type="time" class="input" /></div>
        <div class="field"><label for="ev-e">{{ t('agenda.evEnd') }}</label><input id="ev-e" v-model="form.end" type="time" class="input" /></div>
      </div>
      <div class="field"><label for="ev-p">{{ t('agenda.evPlace') }}</label><input id="ev-p" v-model="form.location" class="input" autocomplete="off" /></div>
      <div class="field">
        <label for="ev-c">{{ t('agenda.evCalendar') }}</label>
        <select id="ev-c" v-model="form.calendar" class="input" required><option v-for="s in store.sources" :key="s.id" :value="s.id">{{ s.label }}</option></select>
      </div>
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
.when { font-size: var(--fs-md); flex: 1; min-width: 200px; text-transform: capitalize; }
.sources { display: flex; gap: var(--space-2); flex-wrap: wrap; margin-bottom: var(--space-4); }
.note { margin-top: var(--space-4); }
.detail { display: grid; grid-template-columns: max-content 1fr; gap: var(--space-2) var(--space-4); margin: 0; }
.detail dt { color: var(--c-text-muted); }
.detail dd { margin: 0; display: flex; gap: var(--space-2); align-items: center; }
.form { display: grid; gap: var(--space-3); }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }
</style>
