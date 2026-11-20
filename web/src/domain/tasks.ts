import { isoDay } from './dates'
import type { Task } from './types'

export type TaskSort = 'due' | 'priority' | 'project'

export interface TaskFilters {
  projects: string[]
  tags: string[]
  showDone: boolean
}

/** Filtres cumulables (F-33) : un projet ET tous les tags choisis. */
export function filterTasks(tasks: Task[], f: TaskFilters): Task[] {
  return tasks.filter(
    (t) =>
      (f.showDone || !t.done) &&
      (f.projects.length === 0 || (t.project !== null && f.projects.includes(t.project))) &&
      f.tags.every((tag) => t.tags.includes(tag)),
  )
}

export function sortTasks(tasks: Task[], by: TaskSort): Task[] {
  const due = (t: Task) => t.dueAt ?? '9999-12-31'
  const cmp: Record<TaskSort, (a: Task, b: Task) => number> = {
    due: (a, b) => due(a).localeCompare(due(b)) || a.priority - b.priority,
    priority: (a, b) => a.priority - b.priority || due(a).localeCompare(due(b)),
    project: (a, b) => (a.project ?? '~').localeCompare(b.project ?? '~') || due(a).localeCompare(due(b)),
  }
  return [...tasks].sort(cmp[by])
}

/** Niveau d'urgence déterministe : échéance + priorité manuelle + retard (§4.4). L'IA ne le fixe pas. */
export function urgency(t: Task, now: Date): number {
  if (t.done) return -1
  const today = isoDay(now)
  const lateBoost = t.dueAt && t.dueAt < today ? 100 : 0
  const soon = t.dueAt && t.dueAt <= today ? 20 : t.dueAt ? 5 : 0
  return lateBoost + soon + (5 - t.priority) * 3
}
