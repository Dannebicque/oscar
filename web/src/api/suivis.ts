import type { Suivi } from '../domain/types'

/** Contrat avec l'API Symfony (API Platform) : GET /api/suivis, POST /api/suivis/{id}/close. */
export interface SuivisApi {
  list(): Promise<Suivi[]>
  close(id: string): Promise<void>
}

const SEED: Suivi[] = [
  { id: 's1', subject: 'Planning des soutenances', contact: 'Sophie Laurent', state: 'toHandle', dueAt: '2026-11-20', suggestedByAi: false },
  { id: 's2', subject: 'Devis des affiches', contact: 'Marc Dubois', state: 'relanceDue', dueAt: '2026-11-19', suggestedByAi: true },
  { id: 's3', subject: 'Salle B104 pour le jury', contact: 'Scolarité', state: 'late', dueAt: '2026-11-17', suggestedByAi: false },
  { id: 's4', subject: 'Convention de stage', contact: 'Claire Petit', state: 'waiting', dueAt: '2026-11-24', suggestedByAi: false },
  { id: 's5', subject: 'Matériel de TP', contact: 'Service achats', state: 'done', dueAt: '2026-11-12', suggestedByAi: false },
]

/** Implémentation locale utilisée tant que l'API n'est pas branchée. */
export function createMockApi(): SuivisApi {
  let data = structuredClone(SEED)
  return {
    async list() {
      return structuredClone(data)
    },
    async close(id) {
      data = data.map((s) => (s.id === id ? { ...s, state: 'done' as const } : s))
    },
  }
}

export const api: SuivisApi = createMockApi()
