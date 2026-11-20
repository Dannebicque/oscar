import type { Suivi, SuiviState } from './types'

/** Ordre d'affichage : ce qui demande une action d'abord, le reste ensuite. */
const ORDER: Record<SuiviState, number> = {
  toHandle: 0,
  late: 1,
  relanceDue: 2,
  waiting: 3,
  done: 4,
}

export function sortByPriority(items: Suivi[]): Suivi[] {
  return [...items].sort(
    (a, b) => ORDER[a.state] - ORDER[b.state] || a.dueAt.localeCompare(b.dueAt),
  )
}

export function needsAction(s: Suivi): boolean {
  return s.state === 'toHandle' || s.state === 'late' || s.state === 'relanceDue'
}
