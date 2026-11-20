import type {
  AppNotification, CalEvent, CalendarAccount, CalendarSource, ClaimAction, Connection, JournalEntry, LogEntry, Mail, MailAccount,
  MailHints, NewCalendarAccount, NewMailAccount, NewSuivi, SharedMailboxInfo, Suivi, Task,
} from '../domain/types'

export type SuiviPatch = Partial<
  Pick<Suivi, 'status' | 'noRelance' | 'relanceAt' | 'dueAt' | 'replyReceived' | 'responsible' | 'relanceCount'>
>

/** Erreur de l'API : statut HTTP et message déjà lisible (« Claire s'en occupe »). */
export class ApiError extends Error {
  constructor(public readonly status: number, message: string, public readonly data: Record<string, unknown> = {}) {
    super(message)
  }
}

export interface SuivisApi {
  list(): Promise<Suivi[]>
  create(n: NewSuivi): Promise<Suivi>
  update(id: string, patch: SuiviPatch, journal?: JournalEntry): Promise<Suivi>
  close(id: string): Promise<void>
  /** Brouillon de relance par l'IA ; null si l'IA est coupée ou désactivée : le front garde son modèle local. */
  draftRelance(id: string): Promise<string | null>
}

export interface SendPayload {
  to: string[]
  cc: string[]
  bcc: string[]
  subject: string
  body: string
  /** Délai de relance en heures ; absent = ne pas suivre. */
  followHours?: number
  followSuggestedByAi?: boolean
  inReplyToMailId?: string
}

export interface MailApi {
  list(): Promise<Mail[]>
  /** Corps en texte brut, lu à la demande (jamais de HTML). */
  body(id: string): Promise<string[]>
  /** Suggestions de l'IA pour ce mail (tâche, événement, créneaux) ; vide si l'IA est coupée. */
  hints(id: string): Promise<MailHints>
  markRead(id: string): Promise<void>
  /** Boîte partagée : prise en charge. Lève ApiError 409 (« Claire s'en occupe ») si quelqu'un d'autre l'a prise. */
  claim(mailId: string, suiviId: string | null, action: ClaimAction): Promise<void>
  addNote(mailId: string, suiviId: string | null, text: string): Promise<void>
  /** Envoie puis, seulement si le message est parti, crée le suivi demandé. */
  send(p: SendPayload): Promise<{ followDueAt: string | null }>
}

export interface TasksApi {
  list(): Promise<Task[]>
  create(t: Omit<Task, 'id'>): Promise<Task>
  update(id: string, patch: Partial<Task>): Promise<Task>
}

export interface AgendaApi {
  sources(): Promise<CalendarSource[]>
  events(): Promise<CalEvent[]>
  create(e: Omit<CalEvent, 'id'>): Promise<CalEvent>
}

export interface AccountsApi {
  mail: {
    list(): Promise<MailAccount[]>
    create(a: NewMailAccount): Promise<MailAccount>
    remove(id: string): Promise<void>
    test(id: string): Promise<{ ok: boolean }>
  }
  calendar: {
    list(): Promise<CalendarAccount[]>
    create(a: NewCalendarAccount): Promise<CalendarAccount>
    remove(id: string): Promise<void>
  }
  shared: { list(): Promise<SharedMailboxInfo[]> }
}

/** Réglages utiles au serveur (relances, récaps, mode d'IA). Thème et densité restent sur l'appareil. */
export type ServerSettings = Record<string, string | number | boolean>

export interface SettingsApi {
  get(): Promise<ServerSettings>
  update(patch: ServerSettings): Promise<ServerSettings>
}

export interface SystemApi {
  notifications(): Promise<AppNotification[]>
  markAllRead(): Promise<void>
  connections(): Promise<Connection[]>
  /** Resynchronise maintenant, puis renvoie l'état des connexions. */
  syncNow(): Promise<Connection[]>
  log(): Promise<LogEntry[]>
}

export interface AuthApi {
  devLogin(username: string): Promise<string>
  exchange(code: string): Promise<string>
  me(): Promise<{ username: string; displayName: string }>
  logout(): Promise<void>
}

export interface Api {
  suivis: SuivisApi
  mails: MailApi
  tasks: TasksApi
  agenda: AgendaApi
  accounts: AccountsApi
  settings: SettingsApi
  system: SystemApi
  auth: AuthApi
}
