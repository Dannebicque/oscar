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
/** Dossier personnel (et sous-dossier) de la messagerie : `f:` suivi du chemin sur le serveur, par exemple `f:Projets/Jury`. */
export type CustomFolder = `f:${string}`
/** Où l'on peut déplacer un mail. */
export type MoveTarget = 'inbox' | 'archive' | 'trash' | CustomFolder
/** Un dossier de la messagerie, tel que le serveur le décrit. */
export interface FolderInfo { path: string; name: string; delimiter: string; system: boolean; count: number }

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
  /** Taille maximale des pièces jointes d'un message, en Mo (selon le fournisseur) ; null = 10 Mo. */
  attachmentLimitMb?: number | null
  /** Signature de ce compte (F-19) ; vide = signature par défaut. */
  signature?: string | null
  /** Délai de relance propre à ce compte ; null = réglage de l'utilisateur (F-12, F-54). */
  relanceHours?: number | null
  businessDays?: boolean | null
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
  /** Règle de création automatique des suivis de cette boîte (F-66). */
  autoFollow?: boolean
  slug: string
  address: string
  name: string
  role: 'member' | 'admin'
  aiEnabled: boolean
  /** Identité d'envoi de l'équipe (« De : contact@ ») : nom affiché et signature réglés par un administrateur. */
  senderName?: string | null
  signature?: string | null
  identityId?: string
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

export interface MailInvitation {
  uid: string
  title: string
  start: string
  end: string
  location: string | null
  organizerName: string
  organizerAddress: string
}
export type InvitationResponse = 'accepted' | 'tentative' | 'declined'

export interface Mail {
  id: string
  /** Invitation d'agenda jointe (F-25), lue à la demande ; null = aucune. */
  invitation?: MailInvitation | null
  invitationResponse?: InvitationResponse | null
  /** Message-ID IMAP : identifie la demande même reçue dans plusieurs boîtes (F-44). Absent des données simulées. */
  messageId?: string
  /** Identifiants des messages auxquels celui-ci répond (In-Reply-To, References) : rattache une réponse à son fil. */
  parents?: string[]
  /** 'personal' ou id d'une boîte partagée. */
  mailbox: string
  folder: MailFolder | CustomFolder
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
  /** Participants (organisateur compris), quand le serveur d'agenda les donne : sert à retrouver les échanges de mails. */
  attendees?: { name: string; address: string }[]
}

/** Identité d'expéditeur (F-19) : nom affiché, adresse « De », « Répondre à » et signature, par compte de messagerie. */
export interface Identity {
  id: string
  accountId: string
  name: string
  address: string
  replyTo: string | null
  /** Texte brut inséré à la rédaction (dérivé du HTML quand la signature est en HTML). */
  signature: string | null
  /** Signature en HTML assaini ; null = signature en texte seul. */
  signatureHtml?: string | null
  isDefault: boolean
  /** Identité principale du compte : son adresse, non supprimable. */
  primary: boolean
  /** Identité d'une boîte partagée (lecture seule ici, réglée par un administrateur de la boîte). */
  shared?: boolean
}
export type IdentityInput = Pick<Identity, 'name' | 'address' | 'replyTo' | 'signature'> & { signatureHtml?: string | null; accountId?: string }

export type NotificationKind = 'replyReceived' | 'relanceDue' | 'invitation' | 'connectionFailed' | 'takenOver' | 'memberReplied' | 'scheduledFailed' | 'meetingSoon' | 'sharedAdded'
export interface AppNotification {
  id: string
  kind: NotificationKind
  /** Paramètres du message (qui, objet, nombre) : le texte est composé côté front, en français ou en anglais. */
  params: Record<string, string | number>
  at: string
  read: boolean
  to: string
  /** Canaux choisis pour ce type (F-75) : absent = affiché partout. */
  inApp?: boolean
  desktop?: boolean
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

/** Ce que l'instance rend public : nom affiché, inscriptions, liens légaux, et si l'assistant d'installation doit s'ouvrir. */
export interface InstanceInfo {
  name: string
  registration: boolean
  supportEmail: string
  legalUrl: string
  privacyUrl: string
  aiExternalAllowed: boolean
  defaultAttachmentMb: number
  setupNeeded: boolean
}
/** Réglages globaux modifiables par l'administrateur de l'instance. */
export interface InstanceConfig {
  instanceName: string
  registration: 'open' | 'closed'
  supportEmail: string
  legalUrl: string
  privacyUrl: string
  defaultAttachmentMb: number
  aiExternalAllowed: boolean
  setupDone: boolean
}

/** Un compte vu par l'administrateur : jamais de contenu, pas même le nombre de mails ou de tâches. */
export interface AdminUser {
  id: string
  name: string
  email: string | null
  username: string
  admin: boolean
  disabled: boolean
  /** Compte local (mot de passe) ; sinon connexion par l'établissement. */
  local: boolean
  createdAt: string
  lastLoginAt: string | null
  mailAccounts: number
}
export interface HealthCheck { id: string; status: 'ok' | 'warn' | 'error'; data: Record<string, number | string | null> }
export interface HealthReport {
  version: string
  php: string
  checks: HealthCheck[]
  counts: { users: number; disabled: number; admins: number; mailAccounts: number; activeLast30Days: number }
}
export interface AuditEntry { id: string; at: string; actor: string; action: string; target: string | null; details: Record<string, unknown> }
