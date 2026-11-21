<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import TaskDialog from '../components/tasks/TaskDialog.vue'
import AiBadge from '../components/ui/AiBadge.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseDialog from '../components/ui/BaseDialog.vue'
import CheckboxField from '../components/ui/CheckboxField.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SelectField from '../components/ui/SelectField.vue'
import ToneChip from '../components/ui/ToneChip.vue'
import { useFormat } from '../composables/useFormat'
import { clock } from '../domain/clock'
import { isoDay } from '../domain/dates'
import type { Task } from '../domain/types'
import type { TaskSort } from '../domain/tasks'
import { useOscar } from '../oscar/useOscar'
import { useRouter } from 'vue-router'
import { useSuivisStore } from '../stores/suivis'
import Callout from '../components/ui/Callout.vue'
import { useTasksStore } from '../stores/tasks'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const { say } = useOscar()
const fmt = useFormat()
const router = useRouter()
const store = useTasksStore()
const suivis = useSuivisStore()
const ui = useUiStore()
const title = ref('')
const editing = ref<Task | null>(null)
const dialogOpen = ref(false)
const confirmDelete = ref<Task | null>(null)
onMounted(() => Promise.all([store.load(), suivis.load()]))

const today = computed(() => isoDay(clock.now()))
type View = 'open' | 'late' | 'today' | 'soon' | 'done'
const view = ref<View>('open')
const selectedId = ref<string | null>(null)
watch(view, (v) => { store.filters.showDone = v === 'done' }, { immediate: true })
const isLate = (x: Task) => !x.done && x.dueAt !== null && x.dueAt < today.value
const inView = (x: Task, v: View) => v === 'done' ? x.done : x.done ? false : v === 'open' ? true : v === 'late' ? isLate(x) : v === 'today' ? x.dueAt === today.value : x.dueAt !== null && x.dueAt > today.value
const viewCounts = computed(() => Object.fromEntries((['open', 'late', 'today', 'soon', 'done'] as View[]).map((v) => [v, store.items.filter((x) => inView(x, v) && (v === 'done' || !x.toConfirm || v === 'open')).length])) as Record<View, number>)
const shown = computed(() => store.visible.filter((x) => inView(x, view.value)))
const selected = computed(() => store.items.find((x) => x.id === selectedId.value) ?? null)
const sourceRoute = computed(() => (selected.value?.origin === 'mail' ? { name: 'mails' } : selected.value?.origin === 'event' ? { name: 'agenda' } : null))
const groups = computed(() => {
  if (view.value !== 'open') return shown.value.length ? [{ key: view.value === 'done' ? 'done' : view.value, items: shown.value }] : []
  const g = { late: [] as Task[], today: [] as Task[], later: [] as Task[], noDue: [] as Task[] }
  for (const task of shown.value) {
    if (!task.dueAt) g.noDue.push(task)
    else if (task.dueAt < today.value && !task.done) g.late.push(task)
    else if (task.dueAt === today.value) g.today.push(task)
    else g.later.push(task)
  }
  return (Object.keys(g) as (keyof typeof g)[]).map((k) => ({ key: k, items: g[k] })).filter((x) => x.items.length)
})
const hasFilter = computed(() => store.filters.projects.length > 0 || store.filters.tags.length > 0)
const sortOpts = computed(() => (['due', 'priority', 'project'] as TaskSort[]).map((v) => ({ value: v, label: t(`tasks.sort${v[0].toUpperCase()}${v.slice(1)}`) })))
const PRIO_TONE = { 1: 'danger', 2: 'warn', 3: 'neutral', 4: 'neutral' } as const
const PRIO_ICON = { 1: 'alert', 2: 'star', 3: 'list', 4: 'list' } as const

const toggle = (list: string[], v: string) => (list.includes(v) ? list.filter((x) => x !== v) : [...list, v])
function openNew() { editing.value = null; dialogOpen.value = true }
function openEdit(task: Task) { editing.value = task; dialogOpen.value = true }
const select = (task: Task) => { selectedId.value = task.id }

async function quickAdd() {
  const v = title.value.trim()
  if (!v) return
  await store.add({ title: v })
  title.value = ''
  ui.notify(say('taskAdded', { title: v }))
}
async function save(data: Partial<Task> & { title: string }) {
  try {
    if (editing.value) await store.update(editing.value.id, data)
    else await store.add(data)
    dialogOpen.value = false
    ui.notify(editing.value ? t('tasks.saved') : say('taskAdded', { title: data.title }))
  } catch { ui.notify(t('tasks.saveFailed'), { tone: 'error' }) }
}
async function remove() {
  const task = confirmDelete.value
  if (!task) return
  confirmDelete.value = null
  dialogOpen.value = false
  await store.remove(task.id)
  ui.notify(t('tasks.deleted', { title: task.title }), { tone: 'info' })
}
async function setDone(task: Task, done: boolean) {
  await store.setDone(task.id, done)
  if (done) ui.notify(say('taskDone', { title: task.title }), { undo: () => store.setDone(task.id, false) })
}
async function confirm(task: Task) { await store.confirm(task.id); ui.notify(t('tasks.confirmed')) }
async function reject(task: Task) { await store.reject(task.id); ui.notify(t('tasks.rejected'), { tone: 'info' }) }
</script>

<template>
  <PageHeader :title="t('tasks.title')">
    <BaseButton variant="primary" icon="plus" :label="t('tasks.newTask')" @click="openNew" />
  </PageHeader>
  <ErrorState v-if="store.error" @retry="store.load()" />
  <div v-else class="layout">
    <aside class="side" :aria-label="t('tasks.filters')">
      <BaseCard class="stack" pad="sm">
        <form class="quick" @submit.prevent="quickAdd">
          <label for="new-task" class="sr-only">{{ t('tasks.addLabel') }}</label>
          <input id="new-task" v-model="title" class="input" :placeholder="t('tasks.addPlaceholder')" autocomplete="off" />
          <BaseButton variant="primary" type="submit" icon="plus" icon-only :label="t('tasks.add')" />
        </form>
        <nav :aria-label="t('tasks.viewsLabel')">
          <ul class="views">
            <li v-for="v in (['open', 'late', 'today', 'soon', 'done'] as const)" :key="v">
              <button type="button" class="view" :aria-pressed="view === v" @click="view = v"><span>{{ t(`tasks.views.${v}`) }}</span><span class="cnt">{{ viewCounts[v] }}</span></button>
            </li>
          </ul>
        </nav>
        <SelectField v-model="store.sort" :label="t('tasks.sort')" :options="sortOpts" />
        <fieldset v-if="store.projects.length" class="chips"><legend>{{ t('tasks.filterProject') }}</legend>
          <BaseButton v-for="p in store.projects" :key="p" :label="p" :pressed="store.filters.projects.includes(p)" @click="store.filters.projects = toggle(store.filters.projects, p)" />
        </fieldset>
        <fieldset v-if="store.tags.length" class="chips"><legend>{{ t('tasks.filterTag') }}</legend>
          <BaseButton v-for="g in store.tags" :key="g" :label="`#${g}`" :pressed="store.filters.tags.includes(g)" @click="store.filters.tags = toggle(store.filters.tags, g)" />
        </fieldset>
        <BaseButton v-if="hasFilter" variant="ghost" icon="x" :label="t('tasks.clearFilters')" @click="store.filters.projects = []; store.filters.tags = []" />
      </BaseCard>
    </aside>

    <section class="list" :aria-label="t('tasks.list')">
      <Callout v-if="suivis.relances.length" :level="2" tone="warn" icon="clock" :title="t('tasks.relancesTitle', { n: suivis.relances.length }, suivis.relances.length)">
        <p>{{ t('tasks.relancesText') }}</p>
        <template #actions><RouterLink class="btn primary" to="/suivis">{{ t('tasks.relancesGo') }}</RouterLink></template>
      </Callout>
      <h2 class="vt">{{ t(`tasks.views.${view}`) }} <span class="n">{{ shown.length }}</span></h2>
      <p v-if="store.loading" class="muted">{{ t('common.loading') }}</p>
      <EmptyState v-else-if="groups.length === 0" icon="task" :text="say(view === 'open' ? 'tasksEmpty' : 'tasksViewEmpty')">
        <BaseButton variant="primary" icon="plus" :label="t('tasks.newTask')" @click="openNew" />
      </EmptyState>
      <section v-for="g in groups" :key="g.key" class="group" :aria-labelledby="`g-${g.key}`">
        <h3 :id="`g-${g.key}`">{{ t(`tasks.groups.${g.key}`) }} <span class="n">{{ g.items.length }}</span></h3>
        <ul class="rows">
          <li v-for="task in g.items" :key="task.id" class="row task" :class="{ dim: task.done, confirm: task.toConfirm, sel: task.id === selectedId }">
            <CheckboxField
              :id="`c-${task.id}`" :model-value="task.done" :disabled="task.toConfirm" hide-label
              :label="task.done ? t('tasks.markUndone', { title: task.title }) : t('tasks.markDone', { title: task.title })" @update:model-value="(v) => setDone(task, v)"
            />
            <button type="button" class="open" :class="{ done: task.done }" :aria-label="t('tasks.open', { title: task.title })" :aria-pressed="task.id === selectedId" @click="select(task)">
              <span class="line"><strong>{{ task.title }}</strong><AiBadge v-if="task.toConfirm" /></span>
              <span class="meta">
                <ToneChip :tone="PRIO_TONE[task.priority]" :icon="PRIO_ICON[task.priority]" :label="t(`tasks.priority.${task.priority}`)" />
                <ToneChip v-if="task.dueAt" :tone="task.dueAt < today && !task.done ? 'danger' : 'neutral'" icon="calendar" :label="fmt.date(task.dueAt)" />
                <ToneChip v-if="task.project" tone="info" icon="folder" :label="task.project" />
                <span v-for="g in task.tags" :key="g" class="muted">#{{ g }}</span>
                <span class="muted">· {{ t(`tasks.origin.${task.origin}`) }}<template v-if="task.source"> · {{ t('tasks.source', { source: task.source }) }}</template></span>
              </span>
            </button>
            <div v-if="task.toConfirm" class="actions">
              <BaseButton variant="primary" :label="t('tasks.confirm')" @click="confirm(task)" />
              <BaseButton :label="t('tasks.reject')" @click="reject(task)" />
            </div>
          </li>
        </ul>
      </section>
    </section>

    <aside class="detail" :aria-label="t('tasks.detail')">
      <h2>{{ t('tasks.detail') }}</h2>
      <p v-if="!selected" class="muted">{{ t('tasks.detailEmpty') }}</p>
      <template v-else>
        <p class="dt"><strong>{{ selected.title }}</strong></p>
        <p v-if="selected.description" class="muted">{{ selected.description }}</p>
        <dl>
          <dt>{{ t('tasks.fDue') }}</dt><dd>{{ selected.dueAt ? fmt.date(selected.dueAt) : t('tasks.noDue') }}</dd>
          <dt>{{ t('tasks.originLabel') }}</dt><dd>{{ t(`tasks.origin.${selected.origin}`) }}<template v-if="selected.source"> · {{ selected.source }}</template></dd>
          <dt>{{ t('tasks.stateLabel') }}</dt><dd>{{ selected.done ? t('tasks.done') : isLate(selected) ? t('tasks.groups.late') : t('tasks.todo') }}</dd>
        </dl>
        <div class="dact">
          <BaseButton v-if="sourceRoute" icon="link" :label="t(`tasks.seeSource.${selected.origin}`)" @click="router.push(sourceRoute!)" />
          <BaseButton variant="primary" :icon="selected.done ? 'refresh' : 'check'" :label="selected.done ? t('tasks.reopen') : t('tasks.markDoneShort')" @click="setDone(selected, !selected.done)" />
          <BaseButton icon="edit" :label="t('tasks.editTask')" @click="openEdit(selected)" />
        </div>
      </template>
    </aside>
  </div>

  <TaskDialog :open="dialogOpen" :task="editing" :projects="store.projects" @close="dialogOpen = false" @save="save" @remove="(x) => (confirmDelete = x)" />
  <BaseDialog :open="confirmDelete !== null" :title="t('tasks.deleteTitle')" :description="t('tasks.deleteText')" @close="confirmDelete = null">
    <p><strong>{{ confirmDelete?.title }}</strong></p>
    <template #footer>
      <BaseButton :label="t('common.cancel')" @click="confirmDelete = null" />
      <BaseButton variant="danger" icon="trash" :label="t('tasks.delete')" @click="remove" />
    </template>
  </BaseDialog>
</template>

<style scoped>
.layout { display: grid; grid-template-columns: 260px minmax(0, 1fr) 320px; gap: var(--space-5); align-items: start; }
.side { position: sticky; top: 0; }
.quick { display: flex; gap: var(--space-2); }
.chips { border: 0; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: var(--space-1); }
.chips legend { width: 100%; font-weight: 600; margin-bottom: var(--space-1); padding: 0; }
.list { display: grid; gap: var(--space-4); min-width: 0; }
.group h2 { font-size: var(--fs-md); margin-bottom: var(--space-2); }
.n { color: var(--c-text-muted); font-weight: 400; }
.task.confirm { border-color: var(--c-ai); background: var(--c-ai-soft); }
.open { flex: 1; min-width: 0; display: grid; gap: var(--space-1); text-align: left; background: transparent; border: 0; padding: var(--space-1) 0; color: inherit; min-height: var(--target); }
.open:hover strong { text-decoration: underline; }
.open.done strong { text-decoration: line-through; color: var(--c-text-muted); }
.line, .meta { display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap; }
.meta { font-size: var(--fs-sm); }
.actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.views { list-style: none; margin: 0; padding: 0; display: grid; gap: 2px; }
.view { display: flex; justify-content: space-between; align-items: center; width: 100%; min-height: var(--target); padding: 0 var(--space-3); border: 0; border-radius: var(--radius-md); background: transparent; color: var(--c-text); font: inherit; text-align: start; cursor: pointer; }
.view:hover { background: var(--c-surface-2); }
.view[aria-pressed='true'] { background: var(--c-primary-soft); color: var(--c-on-primary-soft); font-weight: 800; }
.cnt { font-size: var(--fs-sm); font-weight: 700; }
.vt { font-size: var(--fs-lg); }
.group h3 { font-size: var(--fs-md); margin-bottom: var(--space-2); }
.task.sel { border-color: var(--c-accent); box-shadow: 0 0 0 2px var(--c-accent); }
.detail { position: sticky; top: 0; display: grid; gap: var(--space-3); align-content: start; padding: var(--space-4); background: var(--c-surface); border: var(--bw-card) solid var(--c-border); border-radius: var(--radius-lg); }
.detail h2 { font-size: var(--fs-md); }
.dt { font-size: var(--fs-lg); }
.detail dl { display: grid; grid-template-columns: auto 1fr; gap: var(--space-1) var(--space-3); margin: 0; font-size: var(--fs-sm); }
.detail dt { color: var(--c-text-muted); } .detail dd { margin: 0; font-weight: 700; }
.dact { display: flex; flex-wrap: wrap; gap: var(--space-2); }
@media (max-width: 1280px) { .layout { grid-template-columns: 240px minmax(0, 1fr); } .detail { grid-column: 1 / -1; position: static; } }
@media (max-width: 900px) { .layout { grid-template-columns: minmax(0, 1fr); } .side { position: static; } }
</style>
