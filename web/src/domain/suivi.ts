import { isoDay } from './dates'
import type { Flag, Suivi } from './types'

const RANK: Record<Flag | 'none' | 'done', number> = {
  replyReceived: 0, // F-76 : un élément qui vient de recevoir une réponse remonte en tête
  late: 1,
  relanceDue: 2,
  dueToday: 3,
  none: 4,
  done: 9,
}

/** Signal calculé d'un suivi (jamais stocké : il dépend de la date). */
export function flagOf(s: Suivi, now: Date): Flag | null {
  if (s.status === 'done') return null
  const today = isoDay(now)
  if (s.replyReceived) return 'replyReceived'
  if (s.dueAt < today) return 'late'
  if (s.direction === 'waiting' && !s.noRelance && s.relanceAt !== null && s.relanceAt <= today) return 'relanceDue'
  if (s.dueAt === today) return 'dueToday'
  return null
}

export function rankOf(s: Suivi, now: Date): number {
  if (s.status === 'done') return RANK.done
  const f = flagOf(s, now)
  return f ? RANK[f] : RANK.none
}

/** Vue Priorités (F-70) : retard, relance due, échéance du jour, puis le reste ; traités en dernier. */
export function sortByPriority(items: Suivi[], now: Date): Suivi[] {
  return [...items].sort((a, b) => rankOf(a, now) - rankOf(b, now) || a.dueAt.localeCompare(b.dueAt))
}

export function needsAction(s: Suivi, now: Date): boolean {
  const f = flagOf(s, now)
  return f === 'replyReceived' || f === 'late' || f === 'relanceDue'
}

/**
 * Mode « Relances » (F-63) : relances dues triées par retard, plafonnées par jour.
 * `alreadyProposedToday` compte celles déjà proposées : le plafond limite les propositions, jamais les envois.
 */
export function relanceQueue(items: Suivi[], now: Date, dailyCap: number, alreadyProposedToday = 0): Suivi[] {
  const due = items
    .filter((s) => flagOf(s, now) === 'relanceDue')
    .sort((a, b) => (a.relanceAt ?? '').localeCompare(b.relanceAt ?? ''))
  return due.slice(0, Math.max(0, dailyCap - alreadyProposedToday))
}

/** Groupe par statut pour la vue Tableau (F-70). */
export const BOARD_COLUMNS = ['new', 'inProgress', 'waiting', 'done'] as const
export function groupByStatus(items: Suivi[]) {
  return BOARD_COLUMNS.map((status) => ({ status, items: items.filter((s) => s.status === status) }))
}
