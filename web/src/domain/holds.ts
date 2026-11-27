import type { SlotHold, Suivi } from './types'

/** Au bout de ce délai sans nouvelle, Oscar propose de libérer ou de relancer (jamais de retrait de lui-même). */
export const HOLD_STALE_DAYS = 7

/** Signaux calculés, jamais des états stockés. */
export type HoldSignal = 'replied' | 'closed' | 'stale' | 'pending'

export function holdSignal(h: SlotHold, suivi: Suivi | undefined, replied: boolean, now: Date): HoldSignal {
  if (suivi?.status === 'done') return 'closed'
  if (replied || suivi?.replyReceived) return 'replied'
  return now.getTime() - Date.parse(h.createdAt) >= HOLD_STALE_DAYS * 86_400_000 ? 'stale' : 'pending'
}

/** Deux plages se chevauchent, quel que soit le fuseau dans lequel elles sont écrites. */
export const overlapping = (a: { start: string; end: string }, b: { start: string; end: string }) => Date.parse(a.start) < Date.parse(b.end) && Date.parse(a.end) > Date.parse(b.start)
