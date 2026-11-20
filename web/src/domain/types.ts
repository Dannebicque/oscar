export type SuiviState = 'toHandle' | 'relanceDue' | 'late' | 'waiting' | 'done'

export interface Suivi {
  id: string
  subject: string
  contact: string
  state: SuiviState
  /** ISO date de la prochaine échéance (relance ou réponse attendue). */
  dueAt: string
  /** Suivi proposé par l'IA et validé par l'utilisateur. */
  suggestedByAi: boolean
}
