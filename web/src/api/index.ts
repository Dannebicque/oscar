import { createHttpApi } from './http'
import { createMockApi } from './mock'
import { session } from './session'
import type { Api } from './types'

export { SHARED } from './mock'
export { session } from './session'
export { ApiError } from './types'

/**
 * Par défaut l'application parle à l'API (VITE_API_URL, sinon http://localhost:8000).
 * VITE_USE_MOCK=true force la démo hors ligne ; les tests utilisent toujours des données simulées.
 */
export const apiMode: 'http' | 'mock' = import.meta.env.VITE_USE_MOCK === 'true' || import.meta.env.MODE === 'test' ? 'mock' : 'http'

function build(): Api {
  return apiMode === 'mock' ? createMockApi() : createHttpApi(import.meta.env.VITE_API_URL ?? 'http://localhost:8000')
}

export let api: Api = build()
/** Pour les tests : remplace l'API par une instance fraîche. */
export function resetApi(next: Api = createMockApi()) {
  api = next
}

/** Nom de l'utilisateur connecté, tel qu'il apparaît dans les journaux et « pris par ». */
export const me = () => session.name
