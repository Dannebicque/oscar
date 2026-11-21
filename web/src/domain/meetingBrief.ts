import type { CalEvent, Mail } from './types'

/**
 * Avant un rendez-vous (F-71) : les derniers échanges de mails avec les participants, pour ne pas arriver sans le contexte.
 * Calcul purement local sur les mails déjà chargés : rien n'est envoyé à l'IA (elle ne reçoit jamais titres ni participants d'un agenda).
 */
export interface BriefItem { mail: Mail; direction: 'received' | 'sent'; who: string }

const DAY = 86_400_000
const norm = (a: string) => a.trim().toLowerCase()
const STOP = new Set(['avec', 'pour', 'dans', 'réunion', 'point', 'rendez', 'vous', 'the', 'and'])
const words = (s: string) => s.toLowerCase().split(/[^\p{L}\p{N}]+/u).filter((w) => w.length > 3 && !STOP.has(w))

/** @param me adresses de l'utilisateur, exclues des participants */
export function briefFor(event: CalEvent, mails: Mail[], now: Date, me: string[] = [], max = 3): { people: string[]; items: BriefItem[] } {
  const mine = new Set(me.map(norm))
  const people = (event.attendees ?? []).filter((p) => !mine.has(norm(p.address)))
  const addresses = new Set(people.map((p) => norm(p.address)))
  const since = now.getTime() - 90 * DAY
  const key = words(event.title)
  const items: BriefItem[] = []
  for (const m of mails) {
    if (m.mailbox !== 'personal' || !['inbox', 'sent', 'archive'].includes(m.folder) || new Date(m.at).getTime() < since) continue
    const from = norm(m.from.address)
    const toMatch = m.to.find((x) => addresses.has(norm(x.address)))
    let who: string | null = null
    if (addresses.has(from)) who = m.from.name || m.from.address
    else if (toMatch) who = toMatch.name || toMatch.address
    // Sans participants connus : on se rabat sur le titre de l'événement dans l'objet des mails.
    else if (addresses.size === 0 && key.length && key.some((w) => m.subject.toLowerCase().includes(w))) who = m.from.name || m.from.address
    if (who) items.push({ mail: m, direction: m.folder === 'sent' || mine.has(from) ? 'sent' : 'received', who })
  }
  items.sort((a, b) => b.mail.at.localeCompare(a.mail.at))
  return { people: people.map((p) => p.name || p.address), items: items.slice(0, max) }
}
