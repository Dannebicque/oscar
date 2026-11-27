import { ApiError } from './api'
import { i18n } from './i18n'
import { useUiStore } from './stores/ui'

/**
 * Filet de sécurité (F-79) : une action qui échoue sans l'avoir prévu le dit, au lieu de ne rien faire. Les écrans gardent leurs propres
 * messages, plus précis ; ceci ne rattrape que le reste. Une session expirée est déjà gérée ailleurs.
 */
export function reportFailure(error: unknown): void {
  console.error(error)
  if (error instanceof ApiError && error.status === 401) return
  const ui = useUiStore()
  // Les messages de l'API pour un refus (4xx) sont écrits pour la personne ; une panne (5xx) ne montre jamais de code technique.
  const message = error instanceof ApiError && error.status >= 400 && error.status < 500 && error.message ? error.message : i18n.global.t('common.actionFailed')
  if (!ui.toasts.some((t) => t.message === message)) ui.notify(message, { tone: 'error' })
}
