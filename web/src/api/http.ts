import type { Suivi } from '../domain/types'
import type { SuivisApi } from './types'

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
