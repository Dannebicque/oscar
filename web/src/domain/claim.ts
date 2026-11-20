import type { Claim, JournalEntry, Mail } from './types'

/** Boîte partagée (F-67, F-68) : aucun responsable désigné ; n'importe quel membre prend, libère, reprend. */
export const UNCLAIMED: Claim = { state: 'new', by: null }

export function isTakenByOther(mail: Mail, me: string): boolean {
  return mail.claim?.state === 'taken' && mail.claim.by !== me
}

/** Avertissement « Claire s'en occupe » avant d'écrire sur une demande prise par un autre. */
export function collisionWith(mail: Mail, me: string): string | null {
  if (isTakenByOther(mail, me)) return mail.claim!.by
  if (mail.composing && mail.composing !== me) return mail.composing
  return null
}

function log(mail: Mail, author: string, action: string, at: Date): Mail {
  const entry: JournalEntry = { at: at.toISOString(), author, action }
  return { ...mail, activity: [...mail.activity, entry] }
}

export function take(mail: Mail, me: string, at: Date): Mail {
  return log({ ...mail, claim: { state: 'taken', by: me } }, me, 'take', at)
}
export function release(mail: Mail, me: string, at: Date): Mail {
  return log({ ...mail, claim: UNCLAIMED, composing: null }, me, 'release', at)
}
export function handle(mail: Mail, me: string, at: Date): Mail {
  return log({ ...mail, claim: { state: 'handled', by: me } }, me, 'handled', at)
}
export function reopen(mail: Mail, me: string, at: Date): Mail {
  return log({ ...mail, claim: { state: 'taken', by: me } }, me, 'reopen', at)
}
/** Reprendre la demande d'un autre membre : il en est informé (journal). */
export function takeOver(mail: Mail, me: string, at: Date): Mail {
  return log({ ...mail, claim: { state: 'taken', by: me } }, me, `takeOver:${mail.claim?.by ?? ''}`, at)
}
