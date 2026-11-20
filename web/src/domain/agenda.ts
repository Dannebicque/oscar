import { isoDay } from './dates'
import type { CalEvent, CalendarSource } from './types'

export interface NextEvent {
  current: CalEvent | null
  next: CalEvent | null
  after: CalEvent | null
  /** true si plus aucun rendez-vous aujourd'hui (F-71). */
  noneToday: boolean
}

/** Encart « Prochain RDV » (F-71). L'agenda perso n'est inclus que si le réglage est activé. */
export function nextEvent(events: CalEvent[], sources: CalendarSource[], now: Date, includePersonal: boolean): NextEvent {
  const personal = new Set(sources.filter((s) => s.personal).map((s) => s.id))
  const list = events
    .filter((e) => includePersonal || !personal.has(e.calendar))
    .filter((e) => new Date(e.end) > now)
    .sort((a, b) => a.start.localeCompare(b.start))
  const current = list.find((e) => new Date(e.start) <= now) ?? null
  const upcoming = list.filter((e) => new Date(e.start) > now)
  const next = current ?? upcoming[0] ?? null
  const rest = current ? upcoming : upcoming.slice(1)
  const today = isoDay(now)
  const noneToday = !list.some((e) => isoDay(new Date(e.start)) === today)
  return { current, next, after: rest[0] ?? null, noneToday }
}

/** Créneaux libres/occupés : seule donnée lue par l'IA sur un agenda (§5). */
export function busySlots(events: CalEvent[]): { start: string; end: string }[] {
  return events.map(({ start, end }) => ({ start, end }))
}

/** Conflits d'horaire (F-21) : ids des événements qui en chevauchent un autre. */
export function conflictIds(events: CalEvent[]): Set<string> {
  const out = new Set<string>()
  const sorted = [...events].sort((a, b) => a.start.localeCompare(b.start))
  for (let i = 0; i < sorted.length; i++) {
    for (let j = i + 1; j < sorted.length && sorted[j].start < sorted[i].end; j++) {
      out.add(sorted[i].id)
      out.add(sorted[j].id)
    }
  }
  return out
}
