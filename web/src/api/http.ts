import type {
  AppNotification, CalEvent, CalendarAccount, CalendarSource, Connection, ConnectionId, LogEntry, Mail, MailAccount, MailFolder, Suivi, Task,
} from '../domain/types'
import { authEvents, session } from './session'
import { ApiError, type Api, type SendPayload } from './types'

/** Client HTTP : jeton porteur, erreurs lisibles, retour à la connexion sur 401. */
export function createHttp(baseUrl: string) {
  const root = baseUrl.replace(/\/$/, '')
  return async function request<T>(method: string, path: string, body?: unknown, opts: { merge?: boolean; auth?: boolean } = {}): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' }
    if (body !== undefined) headers['Content-Type'] = opts.merge ? 'application/merge-patch+json' : 'application/json'
    if (opts.auth !== false && session.token) headers.Authorization = `Bearer ${session.token}`
    let res: Response
    try {
      res = await fetch(`${root}${path}`, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) })
    } catch {
      throw new ApiError(0, 'network')
    }
    if (res.status === 401 && opts.auth !== false) authEvents.onUnauthorized()
    if (res.status === 204) return undefined as T
    const data = (await res.json().catch(() => ({}))) as Record<string, unknown>
    if (!res.ok) throw new ApiError(res.status, String(data.title ?? data.detail ?? `${method} ${path} : ${res.status}`), data)
    return data as T
  }
}
type Request = ReturnType<typeof createHttp>

/** Valeurs par défaut pour les champs que l'API omet quand ils sont vides. */
function hydrateSuivi(raw: Partial<Suivi> & { composingName?: string | null }): Suivi {
  return {
    id: '', subject: '', contact: '', direction: 'waiting', status: 'new', dueAt: '', relanceAt: null, relanceCount: 0,
    noRelance: false, responsible: null, mailbox: 'personal', mailId: null, replyReceived: false, suggestedByAi: false,
    reason: null, journal: [], takenByName: raw.takenByName ?? null, notes: raw.notes ?? [], composingName: raw.composingName ?? null, ...raw,
  }
}

interface RawMail {
  id: string
  folder: string
  mailbox?: string
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

const toMail = (r: RawMail): Mail => ({
  id: r.id, messageId: r.messageId, parents: [...(r.references ?? []), ...(r.inReplyTo ? [r.inReplyTo] : [])], mailbox: r.mailbox ?? 'personal',
  folder: FOLDERS[r.folder] ?? 'inbox', from: { name: r.fromName || r.fromAddress, address: r.fromAddress }, to: r.toAddresses.map((a) => ({ name: a, address: a })),
  subject: r.subject, paragraphs: [], at: r.date, unread: false, attachments: [], suivi: null, claim: null, composing: null, notes: [], activity: [], hints: {},
})

/** « Nom <adresse> » ou « adresse » → adresse. Renvoie null si ce n'est pas une adresse. */
export function extractAddress(input: string): string | null {
  const m = input.match(/<([^<>\s]+@[^<>\s]+)>/) ?? input.trim().match(/^([^\s<>,;]+@[^\s<>,;]+)$/)
  return m ? m[1].toLowerCase() : null
}

export function createHttpApi(baseUrl: string): Api {
  const req: Request = createHttp(baseUrl)
  const accountsCache: { mail: MailAccount[] | null } = { mail: null }

  const mailAccounts = async () => (accountsCache.mail ??= await req<MailAccount[]>('GET', '/api/mail-accounts'))

  return {
    suivis: {
      async list() { return (await req<Partial<Suivi>[]>('GET', '/api/suivis')).map(hydrateSuivi) },
      async create(n) { return hydrateSuivi(await req<Partial<Suivi>>('POST', '/api/suivis', { status: 'new', ...n })) },
      async update(id, patch) { return hydrateSuivi(await req<Partial<Suivi>>('PATCH', `/api/suivis/${encodeURIComponent(id)}`, patch, { merge: true })) },
      async close(id) { await req('POST', `/api/suivis/${encodeURIComponent(id)}/close`) },
      async draftRelance(id) {
        try { return (await req<{ body: string }>('POST', `/api/suivis/${encodeURIComponent(id)}/relance-draft`, {})).body } catch (e) {
          if (e instanceof ApiError && (e.status === 403 || e.status === 404)) return null
          throw e
        }
      },
    },
    mails: {
      async list() { return (await req<RawMail[]>('GET', '/api/mails')).map(toMail) },
      async body(id) { return (await req<{ paragraphs: string[] }>('GET', `/api/mails/${encodeURIComponent(id)}/body`)).paragraphs },
      async hints(id) { return req('GET', `/api/mails/${encodeURIComponent(id)}/hints`) },
      async markRead() { /* l'état lu n'est pas encore suivi côté serveur */ },
      async claim(_mailId, suiviId, action) {
        if (!suiviId) throw new ApiError(404, 'no-suivi')
        await req('POST', `/api/suivis/${encodeURIComponent(suiviId)}/claim`, { action })
      },
      async addNote(_mailId, suiviId, text) {
        if (!suiviId) throw new ApiError(404, 'no-suivi')
        await req('POST', `/api/suivis/${encodeURIComponent(suiviId)}/notes`, { text })
      },
      async send(p: SendPayload) {
        const accounts = await mailAccounts()
        if (accounts.length === 0) throw new ApiError(422, 'no-account')
        const r = await req<{ suivi: { dueAt: string } | null }>('POST', '/api/mails/send', { accountId: accounts[0].id, ...p, followHours: p.followHours ?? null })
        return { followDueAt: r.suivi?.dueAt ?? null }
      },
    },
    tasks: {
      list: () => req<Task[]>('GET', '/api/tasks'),
      create: (t) => req<Task>('POST', '/api/tasks', t),
      update: (id, patch) => req<Task>('PATCH', `/api/tasks/${encodeURIComponent(id)}`, patch, { merge: true }),
      remove: async (id) => { await req('DELETE', `/api/tasks/${encodeURIComponent(id)}`) },
    },
    agenda: {
      async sources(): Promise<CalendarSource[]> {
        return (await req<CalendarAccount[]>('GET', '/api/calendar-accounts')).map((a) => ({ id: a.id, label: a.label, kind: a.kind, personal: a.personal }))
      },
      async events(): Promise<CalEvent[]> {
        const rows = await req<{ id: string; calendarId: string; title: string; start: string; end: string; location: string | null }[]>('GET', '/api/calendar-events')
        return rows.map((e) => ({ id: e.id, calendar: e.calendarId, title: e.title, start: e.start, end: e.end, location: e.location ?? null }))
      },
      async create(e) {
        const r = await req<{ id: string; calendarId: string; location: string | null }>('POST', '/api/calendar-events', { calendarId: e.calendar, title: e.title, start: e.start, end: e.end, location: e.location })
        return { ...e, id: r.id, location: r.location ?? null }
      },
    },
    accounts: {
      mail: {
        async list() { return (accountsCache.mail = await req<MailAccount[]>('GET', '/api/mail-accounts')) },
        async create(a) { accountsCache.mail = null; return req<MailAccount>('POST', '/api/mail-accounts', a) },
        async remove(id) { accountsCache.mail = null; await req('DELETE', `/api/mail-accounts/${encodeURIComponent(id)}`) },
        async test(id) { return req<{ ok: boolean }>('POST', `/api/mail-accounts/${encodeURIComponent(id)}/test`, {}) },
      },
      calendar: {
        list: () => req<CalendarAccount[]>('GET', '/api/calendar-accounts'),
        create: (a) => req<CalendarAccount>('POST', '/api/calendar-accounts', { kind: 'caldav', ...a }),
        remove: async (id) => { await req('DELETE', `/api/calendar-accounts/${encodeURIComponent(id)}`) },
      },
      shared: { list: () => req('GET', '/api/shared-mailboxes') },
    },
    settings: {
      get: () => req('GET', '/api/settings'),
      update: (patch) => req('PUT', '/api/settings', patch),
    },
    system: {
      async notifications(): Promise<AppNotification[]> {
        const rows = await req<{ id: string; kind: AppNotification['kind']; params: Record<string, string | number>; to: string; read: boolean; at: string }[]>('GET', '/api/notifications')
        return rows.map((n) => ({ id: n.id, kind: n.kind, params: n.params, to: n.to, read: n.read, at: n.at }))
      },
      async markAllRead() { await req('POST', '/api/notifications/read-all', {}) },
      async connections(): Promise<Connection[]> {
        const rows = await req<{ id: ConnectionId; state: Connection['state']; detail: string; lastOk: string | null }[]>('GET', '/api/connections')
        return rows.map((c) => ({ id: c.id, state: c.state, detail: c.detail, lastOk: c.lastOk }))
      },
      async syncNow() {
        accountsCache.mail = null
        await req('POST', '/api/sync', {})
        return this.connections()
      },
      log: () => req<LogEntry[]>('GET', '/api/log'),
    },
    auth: {
      async devLogin(username) { return (await req<{ token: string }>('POST', '/api/auth/dev-login', { username }, { auth: false })).token },
      async exchange(code) { return (await req<{ token: string }>('POST', '/api/auth/token', { code }, { auth: false })).token },
      me: () => req('GET', '/api/me'),
      async logout() { await req('POST', '/api/auth/logout', {}).catch(() => undefined) },
    },
  }
}
