import type { Mail } from './types'

/**
 * Recherche avancée dans les mails (F-12) : expéditeur, objet, période, pièce jointe, suivi.
 * Les critères viennent du panneau de filtres ou d'opérateurs tapés dans la recherche :
 *   de:claire  objet:devis  apres:2026-11-01  avant:2026-11-30  pj  suivi  nonlu
 * Le reste du texte cherche dans l'objet, l'expéditeur et le corps.
 */
export interface SearchCriteria {
  text: string
  from: string
  subject: string
  /** Jours inclus, au format AAAA-MM-JJ ; chaîne vide = sans borne. */
  after: string
  before: string
  attachment: boolean
  followed: boolean
  unread: boolean
}

export const emptyCriteria = (): SearchCriteria => ({ text: '', from: '', subject: '', after: '', before: '', attachment: false, followed: false, unread: false })

const OPS = /(^|\s)(de|from|objet|subject|apres|après|after|avant|before):("[^"]*"|\S*)/gi
const FLAGS = /(^|\s)(pj|suivi|nonlu|unread)(?=\s|$)/gi
const day = (v: string) => (/^\d{4}-\d{2}-\d{2}$/.test(v) ? v : '')

/** Sépare les opérateurs du texte libre ; les critères du panneau s'y ajoutent. */
export function parseQuery(q: string, base: SearchCriteria = emptyCriteria()): SearchCriteria {
  const c: SearchCriteria = { ...base }
  const strip = (v: string) => v.replace(/^"|"$/g, '').trim()
  let rest = q.replace(OPS, (_m, _sp: string, key: string, value: string) => {
    const k = key.toLowerCase()
    const v = strip(value)
    if (k === 'de' || k === 'from') c.from = v
    else if (k === 'objet' || k === 'subject') c.subject = v
    else if (k === 'apres' || k === 'après' || k === 'after') c.after = day(v)
    else c.before = day(v)
    return ' '
  })
  rest = rest.replace(FLAGS, (_m, _sp: string, key: string) => {
    const k = key.toLowerCase()
    if (k === 'pj') c.attachment = true
    else if (k === 'suivi') c.followed = true
    else c.unread = true
    return ' '
  })
  c.text = rest.replace(/\s+/g, ' ').trim()
  return c
}

export const isActive = (c: SearchCriteria) => !!(c.from || c.subject || c.after || c.before || c.attachment || c.followed || c.unread)

const ymd = (iso: string) => {
  const d = new Date(iso)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export function matches(m: Mail, c: SearchCriteria): boolean {
  const has = (hay: string, needle: string) => !needle || hay.toLowerCase().includes(needle.toLowerCase())
  if (!has(`${m.from.name} ${m.from.address}`, c.from)) return false
  if (!has(m.subject, c.subject)) return false
  if (c.after && ymd(m.at) < c.after) return false
  if (c.before && ymd(m.at) > c.before) return false
  if (c.attachment && m.attachments.length === 0) return false
  if (c.followed && !m.suivi) return false
  if (c.unread && !m.unread) return false
  return has(`${m.subject} ${m.from.name} ${m.from.address} ${m.paragraphs.join(' ')}`, c.text)
}
