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
  /** Renseignés par l'API pour une demande d'alias : « pris par X », notes internes, « X rédige » (F-67 à F-69). */
  takenByName?: string | null
  notes?: Note[]
  composingName?: string | null
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
  /** Position dans le message (téléchargement). */
  index?: number
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

export type ClaimAction = 'take' | 'release' | 'handle' | 'reopen' | 'takeOver'

export interface MailAccount {
  id: string
  label: string
  address: string
  imapHost: string
  imapPort: number
  imapEncryption: 'none' | 'ssl' | 'tls'
  smtpHost: string
  smtpPort: number
  smtpEncryption: 'none' | 'ssl' | 'tls'
  username: string
  aiAllowed: boolean
  lastSyncAt?: string | null
  lastError?: string | null
}
export type NewMailAccount = Omit<MailAccount, 'id' | 'lastSyncAt' | 'lastError'> & { password: string }

export interface CalendarAccount {
  id: string
  label: string
  kind: 'caldav' | 'google'
  url: string
  username: string
  personal: boolean
  lastError?: string | null
}
export type NewCalendarAccount = Omit<CalendarAccount, 'id' | 'kind' | 'lastError'> & { password: string }

export interface SharedMailboxInfo {
  id: string
  slug: string
  address: string
  name: string
  role: 'member' | 'admin'
  aiEnabled: boolean
  members: { username: string; displayName: string; role: 'member' | 'admin' }[]
}

export interface MailHints {
  availability?: MailAvailabilityHint | null
  event?: MailEventHint | null
  task?: MailTaskHint | null
  suggestFollow?: boolean
  /** Résumé de l'IA : les phrases qui portent la demande (absent pour un message court). */
  summary?: string | null
}

export interface Mail {
  id: string
  /** Message-ID IMAP : identifie la demande même reçue dans plusieurs boîtes (F-44). Absent des données simulées. */
  messageId?: string
  /** Identifiants des messages auxquels celui-ci répond (In-Reply-To, References) : rattache une réponse à son fil. */
  parents?: string[]
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
  hints: MailHints
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

export type NotificationKind = 'replyReceived' | 'relanceDue' | 'invitation' | 'connectionFailed' | 'takenOver' | 'memberReplied'
export interface AppNotification {
  id: string
  kind: NotificationKind
  /** Paramètres du message (qui, objet, nombre) : le texte est composé côté front, en français ou en anglais. */
  params: Record<string, string | number>
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
  /** Code du message (authRefused, timeout, unreachable, failed, recapNotSent) : traduit côté front. */
  message: string
  action: string
}
