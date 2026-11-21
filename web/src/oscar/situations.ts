import { ERROR_SITUATIONS } from './rules'

/**
 * Catalogue des situations où Oscar prend la parole. Les phrases vivent dans i18n (`oscar.<situation>.neutral|light`) :
 * `light` est la version avec une pointe d'humour, `neutral` la version sobre (réglage « Ton d'Oscar »).
 * Une situation d'erreur n'a que `neutral` : l'humour disparaît quand quelque chose ne va pas.
 */
export const SITUATIONS = [
  'greeting', 'quiet', 'replyReceived', 'relanceDue', 'relancesMany', 'calm', 'noMeeting', 'availability', 'suggestFollow', 'suggestTask',
  'suggestEvent', 'suggestGroup', 'closed', 'sentFollow', 'followAi', 'recapIntro', 'draftReady', 'neverSend', 'neverDeletes', 'firstLaunch',
  'tasksEmpty', 'tasksViewEmpty', 'taskAdded', 'taskDone', 'summaryDay', 'summaryTeam', 'recapMorning', 'recapEvening', 'journalIntro',
  'errorGeneric', 'errorConnection', 'errorSend',
] as const
export type Situation = (typeof SITUATIONS)[number]
export type OscarTone = 'light' | 'neutral'

export const isError = (s: Situation) => (ERROR_SITUATIONS as readonly string[]).includes(s)
