<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '../components/ui/BaseButton.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import CheckboxField from '../components/ui/CheckboxField.vue'
import ToneChip from '../components/ui/ToneChip.vue'
import AiBadge from '../components/ui/AiBadge.vue'
import { useFormat } from '../composables/useFormat'
import { clock } from '../domain/clock'
import { isoDay } from '../domain/dates'
import type { Task } from '../domain/types'
import { useTasksStore } from '../stores/tasks'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const fmt = useFormat()
const store = useTasksStore()
const ui = useUiStore()
const title = ref('')
onMounted(() => store.load())

const today = computed(() => isoDay(clock.now()))
const groups = computed(() => {
  const g = { late: [] as Task[], today: [] as Task[], later: [] as Task[], noDue: [] as Task[] }
  for (const task of store.visible) {
    if (!task.dueAt) g.noDue.push(task)
    else if (task.dueAt < today.value && !task.done) g.late.push(task)
    else if (task.dueAt === today.value) g.today.push(task)
    else g.later.push(task)
  }
  return (Object.keys(g) as (keyof typeof g)[]).map((k) => ({ key: k, items: g[k] })).filter((x) => x.items.length)
})
const hasFilter = computed(() => store.filters.projects.length > 0 || store.filters.tags.length > 0)

function toggle(list: string[], v: string) {
  return list.includes(v) ? list.filter((x) => x !== v) : [...list, v]
}
async function add() {
  const v = title.value.trim()
  if (!v) return
  await store.add({ title: v })
  title.value = ''
  ui.notify(t('tasks.added', { title: v }))
}
async function setDone(task: Task, done: boolean) {
  await store.setDone(task.id, done)
  if (done) ui.notify(t('tasks.doneToast', { title: task.title }), { undo: () => store.setDone(task.id, false) })
}
async function confirm(task: Task) { await store.confirm(task.id); ui.notify(t('tasks.confirmed')) }
async function reject(task: Task) { await store.reject(task.id); ui.notify(t('tasks.rejected'), { tone: 'info' }) }
</script>

<template>
  <PageHeader :title="t('tasks.title')" />
  <ErrorState v-if="store.error" @retry="store.load()" />
  <template v-else>
    <form class="add" @submit.prevent="add">
      <label for="new-task" class="sr-only">{{ t('tasks.addLabel') }}</label>
      <input id="new-task" v-model="title" class="input" :placeholder="t('tasks.addPlaceholder')" autocomplete="off" />
      <BaseButton variant="primary" type="submit" icon="plus" :label="t('tasks.add')" />
    </form>

    <div class="filters">
      <div class="field inline">
        <label for="sort">{{ t('tasks.sort') }}</label>
        <select id="sort" v-model="store.sort" class="input">
          <option value="due">{{ t('tasks.sortDue') }}</option><option value="priority">{{ t('tasks.sortPriority') }}</option><option value="project">{{ t('tasks.sortProject') }}</option>
        </select>
      </div>
      <fieldset v-if="store.projects.length"><legend>{{ t('tasks.filterProject') }}</legend>
        <BaseButton v-for="p in store.projects" :key="p" :label="p" :pressed="store.filters.projects.includes(p)" @click="store.filters.projects = toggle(store.filters.projects, p)" />
      </fieldset>
      <fieldset v-if="store.tags.length"><legend>{{ t('tasks.filterTag') }}</legend>
        <BaseButton v-for="g in store.tags" :key="g" :label="`#${g}`" :pressed="store.filters.tags.includes(g)" @click="store.filters.tags = toggle(store.filters.tags, g)" />
      </fieldset>
      <CheckboxField v-model="store.filters.showDone" :label="t('tasks.showDone')" />
      <BaseButton v-if="hasFilter" variant="ghost" icon="x" :label="t('tasks.clearFilters')" @click="store.filters.projects = []; store.filters.tags = []" />
    </div>

    <p class="muted" aria-live="polite">{{ t('tasks.counts', { n: store.visible.length }, store.visible.length) }}</p>
    <p v-if="store.loading" class="muted">{{ t('common.loading') }}</p>
    <EmptyState v-else-if="groups.length === 0" icon="task" :text="t('tasks.empty')" />
    <section v-for="g in groups" :key="g.key" class="group" :aria-labelledby="`g-${g.key}`">
      <h2 :id="`g-${g.key}`">{{ t(`tasks.groups.${g.key}`) }}</h2>
      <ul>
        <li v-for="task in g.items" :key="task.id" class="task" :class="{ done: task.done, confirm: task.toConfirm }">
          <CheckboxField
            :id="`c-${task.id}`" :model-value="task.done" :disabled="task.toConfirm" hide-label
            :label="task.done ? t('tasks.markUndone', { title: task.title }) : t('tasks.markDone', { title: task.title })" @update:model-value="(v) => setDone(task, v)"
          />
          <div class="main">
            <p class="line">
              <strong>{{ task.title }}</strong>
              <ToneChip v-if="task.toConfirm" tone="ai" icon="sparkles" :label="t('tasks.toConfirm')" />
              <AiBadge v-if="task.toConfirm" />
            </p>
            <p class="meta muted">
              <span>{{ t(`tasks.priority.${task.priority}`) }}</span>
              <span v-if="task.dueAt"> · {{ t('common.dueOn', { date: fmt.date(task.dueAt) }) }}</span>
              <span v-if="task.project"> · {{ task.project }}</span>
              <span v-for="g in task.tags" :key="g"> · #{{ g }}</span>
              <span> · {{ t(`tasks.origin.${task.origin}`) }}</span>
              <span v-if="task.source"> · {{ t('tasks.source', { source: task.source }) }}</span>
            </p>
          </div>
          <div v-if="task.toConfirm" class="actions">
            <BaseButton variant="primary" :label="t('tasks.confirm')" @click="confirm(task)" />
            <BaseButton :label="t('tasks.reject')" @click="reject(task)" />
          </div>
        </li>
      </ul>
    </section>
  </template>
</template>

<style scoped>
.add { display: flex; gap: var(--space-2); flex-wrap: wrap; margin-bottom: var(--space-4); }
.add .input { flex: 1; min-width: 220px; }
.filters { display: flex; gap: var(--space-4); flex-wrap: wrap; align-items: flex-end; margin-bottom: var(--space-4); }
fieldset { border: 0; padding: 0; margin: 0; display: flex; gap: var(--space-1); flex-wrap: wrap; align-items: center; }
legend { float: left; margin-right: var(--space-2); font-weight: 600; padding: 0; }
.inline { display: flex; gap: var(--space-2); align-items: center; }
.inline .input { width: auto; }
.check { display: flex; gap: var(--space-2); align-items: center; min-height: var(--target); }
.group { margin-top: var(--space-5); }
.group h2 { font-size: var(--fs-md); margin-bottom: var(--space-2); }
ul { display: grid; gap: var(--gap); }
.task { display: flex; gap: var(--space-3); align-items: center; padding: var(--row-y) var(--space-4); background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-md); }
.task.confirm { border-color: var(--c-ai); background: var(--c-ai-soft); }
.task.done .line strong { text-decoration: line-through; color: var(--c-text-muted); }
.main { flex: 1; min-width: 0; }
.line { display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap; }
.meta { font-size: var(--fs-sm); }
.actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }
</style>
