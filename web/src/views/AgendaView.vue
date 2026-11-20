<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseDialog from '../components/ui/BaseDialog.vue'
import BaseIcon from '../components/ui/BaseIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import ToneChip from '../components/ui/ToneChip.vue'
import { useFormat } from '../composables/useFormat'
import { conflictIds } from '../domain/agenda'
import { clock } from '../domain/clock'
import { addDays, atTime, isoDay, sameDay, startOfWeek } from '../domain/dates'
import type { CalEvent } from '../domain/types'
import { useAgendaStore } from '../stores/agenda'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const fmt = useFormat()
const store = useAgendaStore()
const ui = useUiStore()
onMounted(() => store.load())

type View = 'week' | 'day' | 'list'
const view = ref<View>('week')
const anchor = ref(clock.now())
const viewOptions = computed(() => (['week', 'day', 'list'] as View[]).map((v) => ({ value: v, label: t(`agenda.${v}`) })))

const days = computed(() => {
  if (view.value === 'day') return [anchor.value]
  if (view.value === 'list') return Array.from({ length: 14 }, (_, i) => addDays(anchor.value, i))
  const s = startOfWeek(anchor.value)
  return Array.from({ length: 7 }, (_, i) => addDays(s, i))
})
const conflicts = computed(() => conflictIds(store.events))
const forDay = (d: Date) => store.events.filter((e) => sameDay(new Date(e.start), d)).sort((a, b) => a.start.localeCompare(b.start))
const columns = computed(() => days.value.map((d) => ({ d, key: isoDay(d), events: forDay(d) })).filter((c) => view.value !== 'list' || c.events.length))
const heading = computed(() => t('agenda.weekOf', { date: fmt.dateLong(startOfWeek(anchor.value)) }))
const step = computed(() => (view.value === 'day' ? 1 : 7))
const move = (dir: -1 | 1) => { anchor.value = addDays(anchor.value, dir * step.value) }
const label = (e: CalEvent) => store.sourceOf(e.calendar)

const showNew = ref(false)
const form = ref({ title: '', day: isoDay(clock.now()), start: '09:00', end: '10:00', location: '', calendar: 'iut' })
async function create() {
  if (!form.value.title.trim()) return
  const [sh, sm] = form.value.start.split(':').map(Number)
  const [eh, em] = form.value.end.split(':').map(Number)
  const [y, mo, d] = form.value.day.split('-').map(Number)
  const base = new Date(y, mo - 1, d)
  await store.add({ calendar: form.value.calendar, title: form.value.title.trim(), start: atTime(base, sh, sm).toISOString(), end: atTime(base, eh, em).toISOString(), location: form.value.location.trim() || null })
  showNew.value = false
  ui.notify(t('agenda.created', { title: form.value.title }))
  form.value.title = ''
}
</script>

<template>
  <PageHeader :title="t('agenda.title')">
    <BaseButton variant="primary" icon="plus" :label="t('agenda.add')" @click="showNew = true" />
  </PageHeader>
  <ErrorState v-if="store.error" @retry="store.load()" />
  <template v-else>
    <div class="bar">
      <SegmentedControl v-model="view" :options="viewOptions" :label="t('agenda.views')" />
      <div class="nav">
        <BaseButton icon="chevronLeft" icon-only :label="t('agenda.prev')" @click="move(-1)" />
        <BaseButton :label="t('agenda.todayBtn')" @click="anchor = clock.now()" />
        <BaseButton icon="chevronRight" icon-only :label="t('agenda.next')" @click="move(1)" />
      </div>
      <h2 class="when" aria-live="polite">{{ view === 'day' ? fmt.dateLong(anchor) : heading }}</h2>
    </div>
    <ul class="sources" :aria-label="t('agenda.sources')">
      <li v-for="s in store.sources" :key="s.id"><ToneChip :tone="s.personal ? 'ai' : 'info'" :icon="s.personal ? 'user' : 'calendar'" :label="`${s.label} · ${s.kind === 'google' ? 'Google' : 'CalDAV'}`" /></li>
    </ul>

    <p v-if="store.loading" class="muted">{{ t('common.loading') }}</p>
    <EmptyState v-else-if="columns.length === 0" icon="calendar" :text="t('agenda.noEvents')" />
    <div v-else class="grid" :class="view">
      <section v-for="c in columns" :key="c.key" class="day" :class="{ today: sameDay(c.d, clock.now()) }" :aria-labelledby="`d-${c.key}`">
        <h3 :id="`d-${c.key}`">{{ fmt.dateLong(c.d) }}<span v-if="sameDay(c.d, clock.now())" class="today-tag"> · {{ t('common.today') }}</span></h3>
        <ul v-if="c.events.length">
          <li v-for="e in c.events" :key="e.id" class="event" :class="{ personal: label(e)?.personal }">
            <p class="time"><BaseIcon name="clock" :size="14" /> {{ fmt.time(e.start) }} – {{ fmt.time(e.end) }}</p>
            <p class="title">{{ e.title }}</p>
            <p v-if="e.location" class="muted"><BaseIcon name="mapPin" :size="14" /> {{ e.location }}</p>
            <p class="chips">
              <ToneChip :tone="label(e)?.personal ? 'ai' : 'info'" :icon="label(e)?.personal ? 'user' : 'calendar'" :label="label(e)?.label ?? ''" />
              <ToneChip v-if="conflicts.has(e.id)" tone="warn" icon="alert" :label="t('agenda.conflict')" />
            </p>
          </li>
        </ul>
        <p v-else class="muted">{{ t('agenda.noEvents') }}</p>
      </section>
    </div>
    <p class="hint note">{{ t('agenda.freeBusy') }}</p>
  </template>

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
        <select id="ev-c" v-model="form.calendar" class="input"><option v-for="s in store.sources" :key="s.id" :value="s.id">{{ s.label }}</option></select>
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
.grid { display: grid; gap: var(--space-3); }
.grid.week { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); align-items: start; }
.grid.list, .grid.day { grid-template-columns: minmax(0, 1fr); max-width: 720px; }
.day { background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-md); padding: var(--space-3); display: grid; gap: var(--space-2); align-content: start; }
.day.today { border-color: var(--c-primary); box-shadow: inset 0 3px 0 var(--c-primary); }
.day h3 { font-size: var(--fs-sm); text-transform: capitalize; }
.today-tag { color: var(--c-primary); }
ul { display: grid; gap: var(--space-2); }
.event { padding: var(--space-2) var(--space-3); border-radius: var(--radius-sm); background: var(--c-primary-soft); color: var(--c-on-primary-soft); display: grid; gap: 2px; border-left: 4px solid var(--c-primary); }
.event.personal { background: var(--c-ai-soft); color: var(--c-ai); border-left-color: var(--c-ai); }
.event p { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
.time { font-size: var(--fs-sm); font-weight: 700; }
.title { font-weight: 800; color: var(--c-text); }
.event .muted { color: inherit; opacity: 0.9; font-size: var(--fs-sm); }
.chips { gap: var(--space-1); }
.note { margin-top: var(--space-4); }
.form { display: grid; gap: var(--space-3); }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }
</style>
