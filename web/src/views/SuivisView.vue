<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import NewSuiviDialog from '../components/suivis/NewSuiviDialog.vue'
import NextEventCard from '../components/suivis/NextEventCard.vue'
import RelanceBatch from '../components/suivis/RelanceBatch.vue'
import SuiviKpis, { type KpiId } from '../components/suivis/SuiviKpis.vue'
import SuiviHealth from '../components/suivis/SuiviHealth.vue'
import SuiviBoard from '../components/suivis/SuiviBoard.vue'
import SuiviRow from '../components/suivis/SuiviRow.vue'
import Callout from '../components/ui/Callout.vue'
import OscarSays from '../components/ui/OscarSays.vue'
import { useOscar } from '../oscar/useOscar'
import BaseButton from '../components/ui/BaseButton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import { apiMode } from '../api'
import { useFormat } from '../composables/useFormat'
import { useNow } from '../composables/useNow'
import { buildPriorities, type PriorityItem } from '../domain/priorities'
import type { ItemKind, NewSuivi } from '../domain/types'
import { useAccountsStore } from '../stores/accounts'
import { useAgendaStore } from '../stores/agenda'
import { useMailsStore } from '../stores/mails'
import { useSuivisStore, type Scope } from '../stores/suivis'
import { useTasksStore } from '../stores/tasks'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const router = useRouter()
const fmt = useFormat()
const store = useSuivisStore()
const tasks = useTasksStore()
const agenda = useAgendaStore()
const mails = useMailsStore()
const ui = useUiStore()
const { say } = useOscar()
const accounts = useAccountsStore()
const now = useNow()

type View = 'priorities' | 'board' | 'health'
type KindFilter = 'all' | ItemKind
const view = ref<View>('priorities')
const kind = ref<KindFilter>('all')
const kpi = ref<KpiId | null>(null)
const showNew = ref(false)
const showRelances = ref(false)
const suggestion = ref<'open' | 'done' | 'ignored'>('open')

onMounted(() => Promise.all([store.load(), tasks.load(), agenda.load(), mails.load(), accounts.load()]))
const failed = computed(() => store.error || tasks.error || agenda.error)

const all = computed(() => {
  const team = store.scope === 'team'
  return buildPriorities(store.inScope, team ? [] : tasks.items, team ? [] : agenda.work, now.value, true)
})
const open_ = computed(() => all.value.filter((i) => i.status !== 'done'))
const inKpi = (i: PriorityItem, k: KpiId) =>
  k === 'done' ? i.status === 'done' : k === 'late' ? i.flag === 'late' : k === 'relance' ? i.flag === 'relanceDue' : k === 'wait' ? i.status === 'waiting' && i.flag !== 'replyReceived' : i.status !== 'done' && !(i.status === 'waiting' && i.flag !== 'replyReceived')
const kpiCounts = computed<Record<KpiId, number>>(() => ({
  todo: all.value.filter((i) => inKpi(i, 'todo')).length, relance: all.value.filter((i) => inKpi(i, 'relance')).length, late: all.value.filter((i) => inKpi(i, 'late')).length,
  wait: all.value.filter((i) => inKpi(i, 'wait')).length, done: all.value.filter((i) => inKpi(i, 'done')).length,
}))
const base = computed(() => (kpi.value ? all.value.filter((i) => inKpi(i, kpi.value!)) : open_.value))
const counts = computed(() => ({
  all: base.value.length,
  mail: base.value.filter((i) => i.kind === 'mail').length,
  task: base.value.filter((i) => i.kind === 'task').length,
  rdv: base.value.filter((i) => i.kind === 'rdv').length,
}))
const shown = computed(() => (kind.value === 'all' ? base.value : base.value.filter((i) => i.kind === kind.value)))
const kindOptions = computed(() => [
  { value: 'all' as const, label: t('suivis.filterAll'), count: counts.value.all },
  { value: 'mail' as const, label: t('kinds.mail'), count: counts.value.mail },
  { value: 'task' as const, label: t('kinds.task'), count: counts.value.task },
  { value: 'rdv' as const, label: t('kinds.rdv'), count: counts.value.rdv },
])
const scopeOptions = computed(() => [
  { value: 'mine' as Scope, label: t('suivis.mine') },
  ...(accounts.shared.length ? [{ value: 'team' as Scope, label: t('suivis.team', { address: accounts.shared[0].address.split('@')[0] + '@' }) }] : []),
])
const viewOptions = computed(() => [
  { value: 'priorities' as View, label: t('suivis.priorities') },
  { value: 'board' as View, label: t('suivis.board') },
  // Le bilan porte sur mes demandes ; la boîte partagée n'a pas de responsable à qui l'attribuer.
  ...(store.scope === 'mine' ? [{ value: 'health' as View, label: t('suivis.health') }] : []),
])
watch(() => store.scope, () => { kpi.value = null; kind.value = 'all'; if (view.value === 'health') view.value = 'priorities' })
const rdvAhead = computed(() => all.value.filter((i) => i.kind === 'rdv' && i.flag === 'dueToday').length)
const teamNew = computed(() => mails.items.filter((m) => m.claim?.state === 'new').length)
/** Synthèse d'Oscar (maquette) : comptes locaux, aucun contenu de mail ne la nourrit. */
const summary = computed((): { situation: 'summaryTeam' | 'summaryDay'; params: Record<string, string | number> } | null => {
  if (store.scope === 'team') return teamNew.value ? { situation: 'summaryTeam' as const, params: { n: teamNew.value } } : null
  const first = open_.value[0]
  if (!first) return null
  return { situation: 'summaryDay' as const, params: { rdv: t('suivis.summaryRdv', { n: rdvAhead.value }, rdvAhead.value), actions: t('suivis.summaryActions', { n: store.actionCount }, store.actionCount), first: first.title } }
})
const groupOpen = computed(() => apiMode === 'mock' && suggestion.value === 'open' && store.scope === 'mine')
const recent = computed(() =>
  store.inScope.flatMap((s) => s.journal.map((j) => ({ ...j, subject: s.subject }))).sort((a, b) => b.at.localeCompare(a.at)).slice(0, 5),
)

/** Boîte partagée : l'état prime sur le responsable, qui n'existe pas (F-67). */
function teamExtra(i: PriorityItem): string | undefined {
  if (store.scope !== 'team') return undefined
  const m = mails.items.find((x) => x.suivi === i.refId)
  if (!m?.claim) return undefined
  const age = Math.round((now.value.getTime() - new Date(m.at).getTime()) / 3_600_000)
  const who = m.claim.by ?? ''
  const state = m.claim.state === 'new' ? t('suivis.teamOwnerNone') : m.claim.state === 'taken' ? t('suivis.teamTakenBy', { who }) : t('suivis.teamHandledBy', { who })
  return `${state} · ${t('suivis.teamAge', { n: age })}`
}

function openMail(ref: string | null | undefined) {
  const m = mails.find(ref)
  return router.push(m ? { name: 'mails', params: { id: m.id } } : { name: 'mails' })
}
function open(i: PriorityItem) {
  if (i.kind === 'mail') {
    const s = store.byId(i.refId)
    return openMail(s?.mailId)
  }
  return router.push({ name: i.route.name })
}
async function done(i: PriorityItem) {
  if (i.kind === 'mail') {
    const subject = i.title
    const undo = await store.close(i.refId)
    ui.notify(say('closed', { subject }), { undo: undo && (async () => { await undo(); ui.notify(t('suivis.reopened')) }) })
  } else if (i.kind === 'task') {
    await tasks.setDone(i.refId, true)
    ui.notify(say('taskDone', { title: i.title }), { undo: () => tasks.setDone(i.refId, false) })
  }
}
async function create(n: NewSuivi) {
  const s = await store.create(n)
  showNew.value = false
  ui.notify(t('suivis.created', { subject: s.subject }))
}
function suggest(accept: boolean) {
  suggestion.value = accept ? 'done' : 'ignored'
  ui.notify(accept ? t('suivis.suggestionDone') : t('suivis.suggestionIgnored'))
}
</script>

<template>
  <PageHeader :title="t('suivis.title')" :subtitle="t('suivis.subtitle')">
    <BaseButton variant="primary" icon="plus" :label="t('suivis.new')" @click="showNew = true" />
  </PageHeader>

  <ErrorState v-if="failed" @retry="() => { store.load(); tasks.load(); agenda.load() }" />
  <template v-else>
    <div class="toolbar">
      <SegmentedControl v-model="store.scope" :options="scopeOptions" :label="t('suivis.scopeLabel')" />
      <SegmentedControl v-model="view" :options="viewOptions" :label="t('suivis.viewLabel')" />
    </div>

    <div class="layout">
    <div class="stack">
      <SuiviHealth v-if="view === 'health'" @list="view = 'priorities'" />
      <template v-else>
      <NextEventCard v-if="store.scope === 'mine'" :now="now" />

      <Callout :level="2" v-if="store.scope === 'mine' && store.relances.length" tone="warn" icon="clock" :title="t('suivis.relancesTitle', { n: store.relances.length }, store.relances.length)">
        <p>{{ say('relancesMany', { n: store.relances.length }) }}</p>
        <template #actions><BaseButton variant="primary" :label="t('suivis.relancesStart')" @click="showRelances = true" /></template>
      </Callout>


      <SuiviKpis v-model="kpi" :counts="kpiCounts" />
      <OscarSays v-if="summary" :situation="summary.situation" :params="summary.params" suggestion>
        <template #actions><RouterLink class="btn" to="/recap">{{ t('suivis.seeRecap') }}</RouterLink></template>
      </OscarSays>
      <p class="muted" aria-live="polite">{{ t('suivis.actionCount', { n: store.actionCount }, store.actionCount) }}</p>

      <template v-if="view === 'priorities'">
        <SegmentedControl v-model="kind" :options="kindOptions" :label="t('suivis.filterKind')" />
        <p v-if="store.loading" class="muted">{{ t('common.loading') }}</p>
        <EmptyState v-else-if="!kpi && open_.length === 0" :text="say('calm')" />
        <EmptyState v-else-if="shown.length === 0" icon="list" :text="t('suivis.emptyCategory')" />
        <ol v-else class="rows" :aria-label="t('suivis.priorities')">
          <SuiviRow v-for="(i, n) in shown" :key="i.key" :item="i" :rank="n + 1" :extra="teamExtra(i)" @open="open" @done="done" />
        </ol>
      </template>
      <SuiviBoard v-else :items="store.inScope" @open="(s) => openMail(s.mailId)" />
      </template>
    </div>
    <aside v-if="view !== 'health' && (recent.length || groupOpen)" class="aside" :aria-label="t('suivis.activity')">
      <h2 id="act-h">{{ t('suivis.activity') }}</h2>
      <ul v-if="recent.length" class="activity">
        <li v-for="(j, n) in recent" :key="n"><span class="muted">{{ fmt.dateTime(j.at) }}</span><br /><strong>{{ j.author }}</strong> · {{ fmt.journal(j) }} · {{ j.subject }}</li>
      </ul>
      <OscarSays v-if="groupOpen" situation="suggestGroup" suggestion :level="3">
        <template #actions>
          <BaseButton variant="primary" :label="t('suivis.suggestionCreate')" @click="suggest(true)" />
          <BaseButton :label="t('common.ignore')" @click="suggest(false)" />
        </template>
      </OscarSays>
    </aside>
    </div>
  </template>

  <NewSuiviDialog :open="showNew" @close="showNew = false" @create="create" />
  <RelanceBatch :open="showRelances" @close="showRelances = false" />
</template>

<style scoped>
.toolbar { display: flex; gap: var(--space-4); flex-wrap: wrap; margin-bottom: var(--space-4); }
.layout { display: grid; gap: var(--space-4); align-items: start; }
.stack { display: grid; gap: var(--space-4); min-width: 0; }
.aside { display: grid; gap: var(--space-3); padding: var(--space-4); background: var(--c-surface); border: var(--bw-card) solid var(--c-border); border-radius: var(--radius-lg); }
@media (min-width: 1200px) { .layout { grid-template-columns: minmax(0, 1fr) 320px; } .aside { position: sticky; top: 0; } }
h2 { margin-bottom: var(--space-2); font-size: var(--fs-md); }
.activity { list-style: none; margin: 0; padding: 0; display: grid; gap: 0; font-size: var(--fs-sm); }
.activity li { padding: var(--space-2) 0; border-top: var(--bw) solid var(--c-border); }
</style>
