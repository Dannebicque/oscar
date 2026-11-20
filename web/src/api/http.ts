import type { Mail, MailFolder, Suivi } from '../domain/types'
import type { MailApi, SuivisApi } from './types'

/** Valeurs par défaut pour les champs que l'API ne fournit pas encore (journal, responsable, …). */
function hydrate(raw: Partial<Suivi>): Suivi {
  return {
    id: '', subject: '', contact: '', direction: 'waiting', status: 'new', dueAt: '', relanceAt: null, relanceCount: 0,
    noRelance: false, responsible: null, mailbox: 'personal', mailId: null, replyReceived: false, suggestedByAi: false,
    reason: null, journal: [], ...raw,
  }
}

async function json<T>(res: Response, what: string): Promise<T> {
  if (!res.ok) throw new Error(`${what} : ${res.status}`)
  return (await res.json()) as T
}

/** Client HTTP de l'API Symfony pour les suivis (les autres domaines restent simulés pour l'instant). */
export function createHttpSuivisApi(baseUrl: string): SuivisApi {
  const root = baseUrl.replace(/\/$/, '')
  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' }
  return {
    async list() {
      const rows = await json<Partial<Suivi>[]>(await fetch(`${root}/api/suivis`, { headers }), 'GET /api/suivis')
      return rows.map(hydrate)
    },
    async create(n) {
      const res = await fetch(`${root}/api/suivis`, { method: 'POST', headers, body: JSON.stringify({ status: 'new', ...n }) })
      return hydrate(await json<Partial<Suivi>>(res, 'POST /api/suivis'))
    },
    async update(id, patch) {
      const res = await fetch(`${root}/api/suivis/${encodeURIComponent(id)}`, {
        method: 'PATCH', headers: { ...headers, 'Content-Type': 'application/merge-patch+json' }, body: JSON.stringify(patch),
      })
      return hydrate(await json<Partial<Suivi>>(res, `PATCH /api/suivis/${id}`))
    },
    async close(id) {
      const res = await fetch(`${root}/api/suivis/${encodeURIComponent(id)}/close`, { method: 'POST' })
      if (!res.ok) throw new Error(`POST /api/suivis/${id}/close : ${res.status}`)
    },
  }
}

interface RawMail {
  id: string
  folder: string
  messageId: string
  inReplyTo?: string | null
  references?: string[]
  fromName: string
  fromAddress: string
  toAddresses: string[]
  subject: string
  date: string
}

const FOLDERS: Record<string, MailFolder> = { INBOX: 'inbox', Sent: 'sent', Drafts: 'drafts', Archive: 'archive', Trash: 'trash' }

const unsupported = (what: string) => Promise.reject(new Error(`unsupported:${what}`))

/**
 * Mails lus sur l'API (en-têtes synchronisés par IMAP). Pas encore côté serveur : envoi SMTP, boîtes partagées,
 * notes et suggestions de l'IA ; ces actions échouent clairement au lieu de faire semblant.
 */
export function createHttpMailApi(baseUrl: string): MailApi {
  const root = baseUrl.replace(/\/$/, '')
  return {
    async list() {
      const rows = await json<RawMail[]>(await fetch(`${root}/api/mails`, { headers: { Accept: 'application/json' } }), 'GET /api/mails')
      return rows.map((r): Mail => ({
        id: r.id, messageId: r.messageId, parents: [...(r.references ?? []), ...(r.inReplyTo ? [r.inReplyTo] : [])], mailbox: 'personal', folder: FOLDERS[r.folder] ?? 'inbox',
        from: { name: r.fromName || r.fromAddress, address: r.fromAddress }, to: r.toAddresses.map((a) => ({ name: a, address: a })),
        subject: r.subject, paragraphs: [], at: r.date, unread: false, attachments: [], suivi: null, claim: null, composing: null,
        notes: [], activity: [], hints: {},
      }))
    },
    async body(id) {
      const res = await json<{ paragraphs: string[] }>(await fetch(`${root}/api/mails/${encodeURIComponent(id)}/body`, { headers: { Accept: 'application/json' } }), 'GET body')
      return res.paragraphs
    },
    async markRead() { /* l'état lu n'est pas encore suivi côté serveur */ },
    async linkSuivi() { /* le lien se déduit du Message-ID du suivi */ },
    setClaim: () => unsupported('claim'),
    addNote: () => unsupported('note'),
    send: () => unsupported('send'),
  }
}
