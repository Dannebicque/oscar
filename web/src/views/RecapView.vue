<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SuggestBadge from '../components/ui/SuggestBadge.vue'
import BaseIcon from '../components/ui/BaseIcon.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import { useFormat } from '../composables/useFormat'
import { clock } from '../domain/clock'
import { isoDay } from '../domain/dates'
import { flagOf } from '../domain/suivi'
import { useOscar } from '../oscar/useOscar'
import { useAccountsStore } from '../stores/accounts'
import { useAgendaStore } from '../stores/agenda'
import { useSettingsStore } from '../stores/settings'
import { useSuivisStore } from '../stores/suivis'
import { useTasksStore } from '../stores/tasks'

const { t } = useI18n()
const { say } = useOscar()
const fmt = useFormat()
const suivis = useSuivisStore()
const tasks = useTasksStore()
const agenda = useAgendaStore()
const settings = useSettingsStore()
const accounts = useAccountsStore()
onMounted(() => Promise.all([suivis.load(), tasks.load(), agenda.load(), accounts.loaded ? Promise.resolve() : accounts.load().catch(() => undefined)]))

type Slot = 'morning' | 'evening'
const slot = ref<Slot>('morning')
const options = computed(() => (['morning', 'evening'] as Slot[]).map((v) => ({ value: v, label: t(`recap.${v}`) })))
const now = clock.now()
type Row = { type: 'mail' | 'rdv' | 'task'; text: string; meta: string; to: string }
const rows = computed(() => {
  const mails: Row[] = suivis.mine.filter((x) => ['replyReceived', 'late'].includes(flagOf(x, now) ?? '')).map((x) => ({ type: 'mail', text: x.subject, meta: x.contact, to: '/suivis' }))
  const events: Row[] = agenda.work.filter((e) => isoDay(new Date(e.start)) === isoDay(now) && new Date(e.end) > now).map((e) => ({ type: 'rdv', text: e.title, meta: fmt.time(e.start), to: '/agenda' }))
  const todo: Row[] = tasks.today.map((x) => ({ type: 'task', text: x.title, meta: x.dueAt ? fmt.date(x.dueAt) : '', to: '/taches' }))
  const reminders: Row[] = suivis.relances.map((x) => ({ type: 'mail', text: x.subject, meta: x.contact, to: '/suivis' }))
  return { mailsToHandle: mails, events, tasks: todo, relances: reminders }
})
const sections = computed(() => (Object.keys(rows.value) as (keyof typeof rows.value)[]).map((key) => ({ key, items: rows.value[key] })))
const actions = computed(() => rows.value.mailsToHandle.length + rows.value.tasks.length + rows.value.relances.length)
const summary = computed(() => {
  const first = rows.value.mailsToHandle[0] ?? rows.value.relances[0] ?? rows.value.tasks[0]
  if (slot.value === 'evening') return say('recapEvening', { done: suivis.items.filter((x) => x.status === 'done').length, left: actions.value })
  return first ? say('recapMorning', { rdv: t('suivis.summaryRdv', { n: rows.value.events.length }, rows.value.events.length), actions: t('suivis.summaryActions', { n: actions.value }, actions.value), first: first.text }) : say('calm')
})
const title = computed(() => t(slot.value === 'morning' ? 'recap.titleMorning' : 'recap.titleEvening', { rdv: rows.value.events.length, n: actions.value }))
const chipTone: Record<string, string> = { mailsToHandle: 'danger', events: 'info', tasks: 'warn', relances: 'warn' }
</script>

<template>
  <PageHeader :title="t('recap.title')" :subtitle="t('recap.preview')">
    <SegmentedControl v-model="slot" :options="options" :label="t('recap.title')" />
    <RouterLink class="btn" to="/reglages">{{ t('recap.settings') }}</RouterLink>
  </PageHeader>
  <article class="card recap" :aria-label="title">
    <header class="mailhead">
      <p v-if="!settings.s.recapEmail" class="muted">{{ t('recap.sentOff') }}</p>
      <p v-else-if="accounts.mail[0]" class="muted">{{ t('recap.sentTo', { address: accounts.mail[0].address, time: slot === 'morning' ? settings.s.recapMorning : settings.s.recapEvening }) }}</p>
      <h2 class="subject">{{ title }}</h2>
    </header>
    <div class="body">
      <p class="sum"><SuggestBadge /> <span>{{ summary }}</span></p>
      <section v-for="s in sections" :key="s.key" :aria-labelledby="`r-${s.key}`" class="sec">
        <h3 :id="`r-${s.key}`"><span class="count" :data-tone="chipTone[s.key]">{{ s.items.length }}</span>{{ t(`recap.${s.key}`) }}</h3>
        <ul v-if="s.items.length" class="lines">
          <li v-for="(i, n) in s.items" :key="n">
            <RouterLink :to="i.to" class="line">
              <span class="type"><BaseIcon :name="i.type === 'mail' ? 'mail' : i.type === 'rdv' ? 'calendar' : 'task'" :size="16" />{{ t(`kinds.${i.type}`) }}</span>
              <span class="text">{{ i.text }}</span><span class="meta muted">{{ i.meta }}</span>
            </RouterLink>
          </li>
        </ul>
        <p v-else class="muted">{{ t('recap.nothing') }}</p>
      </section>
      <p class="muted note">{{ t('recap.readOnly') }}</p>
    </div>
  </article>
</template>

<style scoped>
.recap { padding: 0; overflow: hidden; }
.sec { min-width: 0; }
.line > .meta { overflow-wrap: anywhere; min-width: 0; }
.mailhead { padding: var(--space-3) var(--space-5); border-bottom: var(--bw) solid var(--c-border); background: var(--c-surface-2); }
.subject { font-size: var(--fs-lg); margin-top: var(--space-1); }
.body { padding: var(--space-4) var(--space-5); display: grid; gap: var(--space-4); grid-template-columns: repeat(auto-fit, minmax(min(340px, 100%), 1fr)); align-items: start; }
.sum { grid-column: 1 / -1; flex-wrap: wrap; min-width: 0; display: flex; gap: var(--space-3); align-items: center; padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); background: var(--c-ai-soft); border: var(--bw) solid var(--c-ai); color: var(--c-text); }
.sec h3 { display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-md); }
.count { min-width: 26px; padding: 0 var(--space-2); text-align: center; border-radius: 999px; font-size: var(--fs-sm); font-weight: 800; background: var(--c-neutral-soft); color: var(--c-text); }
.count[data-tone='danger'] { background: var(--c-danger-soft); color: var(--c-danger); }
.count[data-tone='warn'] { background: var(--c-warn-soft); color: var(--c-warn); }
.count[data-tone='info'] { background: var(--c-primary-soft); color: var(--c-on-primary-soft); }
.lines { list-style: none; margin: var(--space-1) 0 0; padding: 0; }
.line { display: flex; align-items: center; gap: var(--space-3); min-height: var(--target); padding: 0 var(--space-1); border-top: var(--bw) solid var(--c-border); color: var(--c-text); text-decoration: none; }
.line:hover .text { text-decoration: underline; }
.type { display: inline-flex; align-items: center; gap: 4px; width: 84px; flex: none; font-size: var(--fs-sm); font-weight: 700; color: var(--c-text-muted); }
.text { flex: 1; min-width: 0; }
.meta { font-size: var(--fs-sm); }
.note { grid-column: 1 / -1; font-size: var(--fs-sm); }
</style>
