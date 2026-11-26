<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { apiMode, session } from '../api'
import VacationBanner from '../components/ui/VacationBanner.vue'
import NextEventCard from '../components/suivis/NextEventCard.vue'
import SuiviRow from '../components/suivis/SuiviRow.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseCard from '../components/ui/BaseCard.vue'
import OscarSays from '../components/ui/OscarSays.vue'
import { useOscar } from '../oscar/useOscar'
import type { Situation } from '../oscar/situations'
import { useSettingsStore } from '../stores/settings'
import BaseIcon from '../components/ui/BaseIcon.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import ToneChip from '../components/ui/ToneChip.vue'
import AiBadge from '../components/ui/AiBadge.vue'
import { useFormat } from '../composables/useFormat'
import { useNow } from '../composables/useNow'
import { buildPriorities, type PriorityItem } from '../domain/priorities'
import { isoDay } from '../domain/dates'
import { useAccountsStore } from '../stores/accounts'
import { useAgendaStore } from '../stores/agenda'
import { useMailsStore } from '../stores/mails'
import { useSuivisStore } from '../stores/suivis'
import { useTasksStore } from '../stores/tasks'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const router = useRouter()
const fmt = useFormat()
const now = useNow()
const suivis = useSuivisStore()
const tasks = useTasksStore()
const agenda = useAgendaStore()
const mails = useMailsStore()
const accounts = useAccountsStore()
const ui = useUiStore()
const settings = useSettingsStore()
const { say } = useOscar()

type Mode = 'auto' | 'busy' | 'calm' | 'first'
const mode = ref<Mode>('auto')
const capture = ref('')
onMounted(() => Promise.all([suivis.load(), tasks.load(), agenda.load(), mails.load(), accounts.load()]))

const priorities = computed(() => buildPriorities(suivis.mine, tasks.items, agenda.work, now.value))
const top = computed(() => priorities.value.filter((i) => i.rank < 4).slice(0, 3))
/** Premier lancement : aucun compte connecté, la page se remplira ensuite (F-72). */
const noAccount = computed(() => apiMode === 'http' && accounts.loaded && accounts.mail.length === 0)
const state = computed<'busy' | 'calm' | 'first'>(() => (mode.value !== 'auto' ? mode.value : noAccount.value ? 'first' : top.value.length ? 'busy' : 'calm'))

/** Texte d'Oscar : une phrase courte, jamais de reproche, une action proposée (§5). */
const speak = (situation: Situation, params: Record<string, string | number> = {}) => ({ situation, params })
const assistant = computed(() => {
  if (state.value === 'first') return speak('firstLaunch')
  const first = suivis.mine.find((s) => suivis.flag(s) === 'replyReceived')
  if (first) return speak('replyReceived', { who: first.contact })
  const rel = suivis.relances[0]
  if (rel) return speak('relanceDue', { who: rel.contact, n: Math.max(1, Math.ceil(settings.s.relanceHours / 24)) })
  return speak('quiet')
})
const today = computed(() => isoDay(now.value))
const counters = computed(() => [
  { key: 'unread', n: mails.unread('personal'), to: '/mails', icon: 'mail' },
  { key: 'shared', n: accounts.shared.reduce((n, b) => n + mails.unread(b.slug), 0), to: '/mails', icon: 'users' },
  { key: 'tasks', n: tasks.today.length, to: '/taches', icon: 'task' },
  { key: 'rdv', n: agenda.work.filter((e) => isoDay(new Date(e.start)) === today.value).length, to: '/agenda', icon: 'calendar' },
])
const demoOptions = computed(() => (['auto', 'busy', 'calm', 'first'] as Mode[]).map((m) => ({ value: m, label: m === 'auto' ? 'Auto' : t(`home.demoStates.${m}`) })))
const steps = computed(() => (['mail', 'calendar', 'rules', 'ai'] as const).map((k) => ({
  key: k, to: k === 'mail' ? '/bienvenue' : '/reglages',
  done: mode.value === 'first' ? false : k === 'mail' ? accounts.mail.length > 0 : k === 'calendar' ? accounts.calendar.length > 0 : state.value !== 'first',
})))
const doneSteps = computed(() => steps.value.filter((s) => s.done).length)

function open(i: PriorityItem) {
  if (i.kind === 'mail') {
    const s = suivis.byId(i.refId)
    const m = mails.find(s?.mailId)
    return router.push(m ? { name: 'mails', params: { id: m.id } } : { name: 'mails' })
  }
  return router.push({ name: i.route.name })
}
async function close(i: PriorityItem) {
  if (i.kind === 'mail') {
    const undo = await suivis.close(i.refId)
    ui.notify(say('closed', { subject: i.title }), { undo: undo && (() => undo()) })
  } else if (i.kind === 'task') {
    await tasks.setDone(i.refId, true)
    ui.notify(say('taskDone', { title: i.title }), { undo: () => tasks.setDone(i.refId, false) })
  }
}
async function quickAdd() {
  const title = capture.value.trim()
  if (!title) return
  await tasks.add({ title })
  capture.value = ''
  ui.notify(t('home.quickDone', { title }))
}
</script>

<template>
  <PageHeader :title="t('home.title')" :subtitle="`${t('home.hello', { name: session.name })} ${fmt.dateLong(now)}`">
    <template v-if="apiMode === 'mock'">
      <span class="demo muted">{{ t('home.demo') }}</span>
      <SegmentedControl v-model="mode" :options="demoOptions" :label="t('home.demo')" />
    </template>
  </PageHeader>

  <div class="grid">
    <VacationBanner class="span" />
    <NextEventCard :now="now" class="span" />

    <OscarSays class="span" :situation="assistant.situation" :params="assistant.params" suggestion>
      <template #actions><BaseButton v-if="state !== 'first'" :label="t('home.assistantRecap')" @click="router.push('/recap')" /></template>
    </OscarSays>

    <section v-if="state === 'busy'" aria-labelledby="first-h">
      <header class="sec-head">
        <h2 id="first-h">{{ t('home.firstTitle') }}</h2>
        <RouterLink to="/suivis">{{ t('home.allSuivis', { n: suivis.mine.length }) }}</RouterLink>
      </header>
      <ol class="rows">
        <SuiviRow v-for="(i, n) in top" :key="i.key" :item="i" :rank="n + 1" compact @open="open" @done="close" />
      </ol>
    </section>

    <BaseCard v-else-if="state === 'calm'" class="stack" aria-labelledby="calm-h">
      <h2 id="calm-h">{{ t('home.calmTitle') }}</h2>
      <p>{{ say('calm') }}</p>
      <ul class="ideas">
        <li><ToneChip tone="neutral" icon="task" :label="t('kinds.task')" /> {{ tasks.items.find((x) => !x.done && x.dueAt)?.title }}</li>
        <li><ToneChip tone="neutral" icon="calendar" :label="t('kinds.rdv')" /> {{ agenda.work[0]?.title }}</li>
      </ul>
      <BaseButton icon="edit" :label="t('home.calmWrite')" @click="router.push('/ecrire')" />
    </BaseCard>

    <BaseCard v-else class="stack" aria-labelledby="start-h">
      <h2 id="start-h">{{ t('home.startTitle') }}</h2>
      <p>{{ t('home.startText', { done: doneSteps }) }}</p>
      <ol class="steps">
        <li v-for="(s, n) in steps" :key="s.key"><span class="n" aria-hidden="true">{{ n + 1 }}</span>{{ t(`home.startSteps.${s.key}`) }} <RouterLink :to="s.to">{{ t('home.startGo') }}</RouterLink></li>
      </ol>
    </BaseCard>

    <BaseCard class="stack glance" aria-labelledby="glance-h">
      <h2 id="glance-h">{{ t('home.glance') }}</h2>
      <ul>
        <li v-for="c in counters" :key="c.key">
          <RouterLink :to="c.to" class="count"><BaseIcon :name="c.icon" :size="18" /><strong>{{ c.n }}</strong><span>{{ t(`home.counters.${c.key}`) }}</span></RouterLink>
        </li>
      </ul>
    </BaseCard>

    <BaseCard class="span stack capture" aria-labelledby="cap-h">
      <h2 id="cap-h">{{ t('home.quickCapture') }}</h2>
      <form @submit.prevent="quickAdd">
        <label for="capture" class="sr-only">{{ t('home.quickPlaceholder') }}</label>
        <input id="capture" v-model="capture" class="input" :placeholder="t('home.quickPlaceholder')" autocomplete="off" />
        <BaseButton variant="primary" type="submit" icon="plus" :label="t('home.quickAdd')" />
      </form>
    </BaseCard>
  </div>
</template>

<style scoped>
.grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: var(--space-4); align-items: start; }
.span { grid-column: 1 / -1; }
.demo { font-size: var(--fs-sm); }
.say { font-size: var(--fs-lg); font-weight: 600; }
.sec-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: var(--space-3); gap: var(--space-3); flex-wrap: wrap; }
h2 { font-size: var(--fs-md); }
.ideas, .steps { display: grid; gap: var(--space-2); }
.steps li { display: flex; gap: var(--space-3); align-items: center; min-height: var(--target); }
.n { width: 28px; height: 28px; border-radius: 50%; background: var(--c-primary-soft); color: var(--c-on-primary-soft); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
.glance ul { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); }
.count { display: flex; flex-direction: column; gap: 2px; min-height: var(--target); padding: var(--space-3); background: var(--c-surface-2); border: var(--bw) solid var(--c-border); border-radius: var(--radius-md); text-decoration: none; color: var(--c-text); }
.count strong { font-size: var(--fs-xl); line-height: 1; }
.count span { font-size: var(--fs-sm); color: var(--c-text-muted); }
.capture form { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.capture .input { flex: 1; min-width: 220px; }
@media (max-width: 900px) { .grid { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 720px) { .grid { gap: var(--space-3); } }
</style>
