import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api'
import { clock } from '../domain/clock'
import { isoDay } from '../domain/dates'
import { filterTasks, sortTasks, type TaskFilters, type TaskSort } from '../domain/tasks'
import type { Task } from '../domain/types'

export const useTasksStore = defineStore('tasks', () => {
  const items = ref<Task[]>([])
  const loading = ref(false)
  const error = ref(false)
  const sort = ref<TaskSort>('due')
  const filters = ref<TaskFilters>({ projects: [], tags: [], showDone: false })

  const visible = computed(() => sortTasks(filterTasks(items.value, filters.value), sort.value))
  const projects = computed(() => [...new Set(items.value.map((t) => t.project).filter((p): p is string => !!p))].sort())
  const tags = computed(() => [...new Set(items.value.flatMap((t) => t.tags))].sort())
  const today = computed(() => {
    const d = isoDay(clock.now())
    return items.value.filter((t) => !t.done && !t.toConfirm && t.dueAt !== null && t.dueAt <= d)
  })
  const toConfirm = computed(() => items.value.filter((t) => t.toConfirm && !t.done))

  async function load() {
    loading.value = true
    error.value = false
    try { items.value = await api.tasks.list() } catch { error.value = true } finally { loading.value = false }
  }
  const put = (t: Task) => { items.value = items.value.map((x) => (x.id === t.id ? t : x)) }

  async function add(t: Partial<Task> & { title: string }) {
    const n = await api.tasks.create({
      description: '', dueAt: null, priority: 3, project: null, tags: [], done: false, origin: 'manual', toConfirm: false, source: null, ...t,
    })
    items.value = [...items.value, n]
    return n
  }
  async function update(id: string, patch: Partial<Task>) { put(await api.tasks.update(id, patch)) }
  async function remove(id: string) { await api.tasks.remove(id); items.value = items.value.filter((t) => t.id !== id) }
  async function setDone(id: string, done: boolean) { put(await api.tasks.update(id, { done })) }
  /** F-35 : accepter une tâche proposée par l'IA. */
  async function confirm(id: string) { put(await api.tasks.update(id, { toConfirm: false })) }
  async function reject(id: string) { put(await api.tasks.update(id, { done: true, toConfirm: false })) }

  return { items, loading, error, sort, filters, visible, projects, tags, today, toConfirm, load, add, update, remove, setDone, confirm, reject }
})
