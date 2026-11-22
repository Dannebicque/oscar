import type { Suivi } from './types'

/**
 * Bilan des suivis : où se perdent les réponses ? Calcul local sur les suivis déjà chargés, lecture seule.
 * Ne désigne personne : on parle de demandes et de délais, jamais de fautes. Aucune relance ni clôture automatique.
 */
const DAY = 86_400_000
const HOUR = 3_600_000

export type Period = 30 | 90 | 0 // jours ; 0 = depuis toujours
export type Verdict = 'fast' | 'ok' | 'slow' | 'silent' | 'waiting'

export interface ContactStat {
  contact: string
  asked: number
  answered: number
  /** Demandes encore ouvertes, sans réponse. */
  open: number
  medianMs: number | null
  relances: number
  /** Ancienneté en jours de la plus ancienne demande ouverte sans réponse. */
  oldestOpenDays: number | null
  verdict: Verdict
}

export interface AgeBuckets { d0_2: number; d3_7: number; d8_14: number; d15: number }

export interface Health {
  asked: number
  answered: number
  /** Part des demandes qui ont reçu une réponse (0 à 1) ; null s'il n'y a aucune demande. */
  rate: number | null
  medianMs: number | null
  open: number
  ages: AgeBuckets
  relances: number
  contacts: ContactStat[]
}

/** Quand la demande est partie : l'envoi, sinon la création. */
export function startOf(s: Suivi): number | null {
  const e = s.journal.find((j) => j.action === 'sent') ?? s.journal.find((j) => j.action === 'created')
  return e ? new Date(e.at).getTime() : null
}
/** Première réponse reçue : le journal la garde même si l'utilisateur a gardé la demande ouverte. */
export function firstReplyAt(s: Suivi): number | null {
  const e = s.journal.find((j) => j.action === 'replyReceived')
  return e ? new Date(e.at).getTime() : null
}

export function median(values: number[]): number | null {
  if (!values.length) return null
  const v = [...values].sort((a, b) => a - b)
  const m = Math.floor(v.length / 2)
  return v.length % 2 ? v[m] : (v[m - 1] + v[m]) / 2
}

/** Une demande « attendue » : l'utilisateur a écrit et attend une réponse (pas un mail reçu à traiter). */
export const isAsk = (s: Suivi) => s.direction === 'waiting' || s.journal.some((j) => j.action === 'sent')

function verdictOf(c: Omit<ContactStat, 'verdict'>): Verdict {
  if (c.answered === 0) return c.asked >= 2 ? 'silent' : 'waiting'
  if (c.medianMs === null) return 'ok'
  if (c.medianMs <= DAY) return 'fast'
  return c.medianMs > 3 * DAY ? 'slow' : 'ok'
}

export function computeHealth(suivis: Suivi[], now: Date, period: Period): Health {
  const t = now.getTime()
  const since = period === 0 ? -Infinity : t - period * DAY
  const asks = suivis.filter((s) => s.mailbox === 'personal' && isAsk(s)).filter((s) => {
    const st = startOf(s)
    return st === null ? period === 0 : st >= since
  })

  const ages: AgeBuckets = { d0_2: 0, d3_7: 0, d8_14: 0, d15: 0 }
  const by = new Map<string, Suivi[]>()
  const delays: number[] = []
  let open = 0
  let answered = 0
  let relances = 0
  for (const s of asks) {
    const list = by.get(s.contact) ?? []
    list.push(s)
    by.set(s.contact, list)
    relances += s.relanceCount
    const reply = firstReplyAt(s)
    const st = startOf(s)
    if (reply !== null) {
      answered++
      if (st !== null && reply >= st) delays.push(reply - st)
    } else if (s.status !== 'done') {
      open++
      const days = st === null ? null : Math.floor((t - st) / DAY)
      if (days !== null) ages[days <= 2 ? 'd0_2' : days <= 7 ? 'd3_7' : days <= 14 ? 'd8_14' : 'd15']++
    }
  }

  const contacts: ContactStat[] = [...by.entries()].map(([contact, list]) => {
    const replied = list.filter((s) => firstReplyAt(s) !== null)
    const waiting = list.filter((s) => firstReplyAt(s) === null && s.status !== 'done')
    const mine = list.flatMap((s) => { const a = startOf(s); const r = firstReplyAt(s); return a !== null && r !== null && r >= a ? [r - a] : [] })
    const oldest = waiting.map(startOf).filter((x): x is number => x !== null)
    const base = {
      contact, asked: list.length, answered: replied.length, open: waiting.length, medianMs: median(mine),
      relances: list.reduce((n, s) => n + s.relanceCount, 0), oldestOpenDays: oldest.length ? Math.floor((t - Math.min(...oldest)) / DAY) : null,
    }
    return { ...base, verdict: verdictOf(base) }
  })
  // À regarder d'abord : ceux qui n'ont jamais répondu, puis les plus lents, puis le reste par nombre de demandes.
  const order: Record<Verdict, number> = { silent: 0, slow: 1, waiting: 2, ok: 3, fast: 4 }
  contacts.sort((a, b) => order[a.verdict] - order[b.verdict] || b.open - a.open || b.asked - a.asked || a.contact.localeCompare(b.contact))

  return { asked: asks.length, answered, rate: asks.length ? answered / asks.length : null, medianMs: median(delays), open, ages, relances, contacts }
}

/** Jours et heures d'une durée, pour l'affichage (« 1 j 4 h »). */
export function durationParts(ms: number): { days: number; hours: number } {
  const days = Math.floor(ms / DAY)
  return { days, hours: Math.floor((ms - days * DAY) / HOUR) }
}
