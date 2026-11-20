import { isoDay, parseDay } from './dates'
import { flagOf, rankOf } from './suivi'
import type { CalEvent, Flag, ItemKind, Suivi, Task } from './types'

/** Élément de la page Suivis : mails, tâches et rendez-vous mélangés (F-70). */
export interface PriorityItem {
  key: string
  /** Identifiant de l'objet source (suivi, tâche ou événement). */
  refId: string
  kind: ItemKind
  title: string
  meta: string
  flag: Flag | null
  rank: number
  dueAt: string
  /** Motif exprimé par l'IA, affiché sous le titre. */
  reason: string | null
  route: { name: string; params?: Record<string, string> }
  suggestedByAi: boolean
}

export function buildPriorities(suivis: Suivi[], tasks: Task[], events: CalEvent[], now: Date): PriorityItem[] {
  const today = isoDay(now)
  const items: PriorityItem[] = []
  for (const s of suivis) {
    if (s.status === 'done') continue
    items.push({
      key: `s:${s.id}`, refId: s.id, kind: 'mail', title: s.subject, meta: s.contact, flag: flagOf(s, now), rank: rankOf(s, now),
      dueAt: s.dueAt, reason: s.reason, route: { name: 'mails', params: s.mailId ? { id: s.mailId } : {} },
      suggestedByAi: s.suggestedByAi,
    })
  }
  for (const t of tasks) {
    if (t.done || t.toConfirm || !t.dueAt) continue
    const flag: Flag | null = t.dueAt < today ? 'late' : t.dueAt === today ? 'dueToday' : null
    items.push({
      key: `t:${t.id}`, refId: t.id, kind: 'task', title: t.title, meta: t.project ?? '', flag,
      rank: flag === 'late' ? 1 : flag === 'dueToday' ? 3 : 4, dueAt: t.dueAt, reason: null,
      route: { name: 'tasks' }, suggestedByAi: false,
    })
  }
  // Rendez-vous : seulement les agendas pro, aujourd'hui et demain (l'agenda perso reste hors suivi, F-71 et réglages).
  const tomorrow = isoDay(new Date(parseDay(today).getTime() + 86_400_000 * 1.5))
  for (const e of events) {
    const d = isoDay(new Date(e.start))
    if (new Date(e.end) < now || d > tomorrow) continue
    items.push({
      key: `e:${e.id}`, refId: e.id, kind: 'rdv', title: e.title, meta: e.location ?? '', flag: d === today ? 'dueToday' : null,
      rank: d === today ? 3 : 4, dueAt: d, reason: null, route: { name: 'agenda' }, suggestedByAi: false,
    })
  }
  return items.sort((a, b) => a.rank - b.rank || a.dueAt.localeCompare(b.dueAt))
}
