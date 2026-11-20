/** Sens du suivi (F-50) : un mail reçu appelle une action de ma part, ou un mail envoyé attend une réponse. */
export type Direction = 'toHandle' | 'waiting'
/** États (F-51). « En retard » et « relance due » ne sont PAS des états : ce sont des signaux calculés. */
export type Status = 'new' | 'inProgress' | 'waiting' | 'done'
/** Signaux calculés, par ordre de priorité décroissant (F-70, F-76). */
export type Flag = 'replyReceived' | 'late' | 'relanceDue' | 'dueToday'
export type ItemKind = 'mail' | 'task' | 'rdv'

export interface JournalEntry {
  at: string // ISO
  author: string
  action: string
}

export interface Suivi {
  id: string
  subject: string
  contact: string
  direction: Direction
  status: Status
  /** Échéance propre à la demande (F-56), AAAA-MM-JJ. */
  dueAt: string
  /** Prochaine relance proposée (F-53), AAAA-MM-JJ, ou null. */
  relanceAt: string | null
  relanceCount: number
  /** « Ne plus relancer » (F-55). */
  noRelance: boolean
  /** null pour un alias : aucun responsable désigné (F-67). */
  responsible: string | null
  /** 'personal' ou l'identifiant d'une boîte partagée. */
  mailbox: string
  mailId: string | null
  replyReceived: boolean
  suggestedByAi: boolean
  /** Motif exprimé par l'IA pour la vue Priorités (F-70). */
  reason: string | null
  journal: JournalEntry[]
}

export type NewSuivi = Pick<Suivi, 'subject' | 'contact' | 'direction' | 'dueAt'> &
  Partial<Pick<Suivi, 'mailId' | 'mailbox' | 'suggestedByAi' | 'reason' | 'relanceAt'>>

export interface Person {
  name: string
  address: string
}

export interface Attachment {
  name: string
  size: string
}

export type MailFolder = 'inbox' | 'sent' | 'drafts' | 'archive' | 'trash'

export type ClaimState = 'new' | 'taken' | 'handled'
export interface Claim {
  state: ClaimState
  by: string | null
}
export interface Note {
  id: string
  author: string
  text: string
  at: string
}

export interface MailAvailabilityHint {
  slots: { start: string; end: string }[]
}
export interface MailEventHint {
  title: string
  start: string
  end: string
  quote: string
}
export interface MailTaskHint {
  title: string
  dueAt: string
  quote: string
}

export interface Mail {
  id: string
  /** 'personal' ou id d'une boîte partagée. */
  mailbox: string
  folder: MailFolder
  from: Person
  to: Person[]
  subject: string
  paragraphs: string[]
  at: string // ISO
  unread: boolean
  attachments: Attachment[]
  suivi: string | null
  /** Seulement pour une boîte partagée. */
  claim: Claim | null
  /** Personne en train d'écrire une réponse (F-68). */
  composing: string | null
  notes: Note[]
  activity: JournalEntry[]
  hints: { availability?: MailAvailabilityHint; event?: MailEventHint; task?: MailTaskHint }
}

export type Origin = 'manual' | 'mail' | 'event' | 'ai'
export interface Task {
  id: string
  title: string
  description: string
  dueAt: string | null
  priority: 1 | 2 | 3 | 4 // 1 = la plus haute
  project: string | null
  tags: string[]
  done: boolean
  origin: Origin
  /** Proposée par l'IA, pas encore confirmée (F-35). */
  toConfirm: boolean
  source: string | null
}

export interface CalendarSource {
  id: string
  label: string
  kind: 'caldav' | 'google'
  /** Agenda perso : exclu de « Prochain RDV » sauf réglage (F-71). */
  personal: boolean
}
export interface CalEvent {
  id: string
  calendar: string
  title: string
  start: string // ISO
  end: string
  location: string | null
}

export type NotificationKind = 'replyReceived' | 'relanceDue' | 'invitation' | 'connectionFailed'
export interface AppNotification {
  id: string
  kind: NotificationKind
  title: string
  at: string
  read: boolean
  to: string
}

export type ConnectionId = 'mail' | 'calendar' | 'google' | 'ai'
export type ConnectionState = 'ok' | 'syncing' | 'error'
export interface Connection {
  id: ConnectionId
  state: ConnectionState
  detail: string
  lastOk: string | null
}
export interface LogEntry {
  id: string
  at: string
  account: string
  type: string
  message: string
  action: string
}
