import { createHttpMailApi, createHttpSuivisApi } from './http'
import { createMockApi } from './mock'
import type { Api } from './types'

export { ME, SHARED } from './mock'

/** VITE_API_URL défini → suivis et mails passent par l'API Symfony (agenda, tâches et alertes restent simulés) ; sinon tout est simulé (démo, tests). */
function build(): Api {
  const mock = createMockApi()
  const url = import.meta.env.VITE_API_URL
  return url ? { ...mock, suivis: createHttpSuivisApi(url), mails: createHttpMailApi(url) } : mock
}

export let api: Api = build()
/** Pour les tests : remplace l'API par une instance fraîche. */
export function resetApi(next: Api = createMockApi()) {
  api = next
}
