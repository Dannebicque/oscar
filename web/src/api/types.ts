import type {
  AppNotification, CalEvent, CalendarSource, Claim, Connection, JournalEntry, LogEntry, Mail, NewSuivi, Note, Suivi, Task,
} from '../domain/types'

export type SuiviPatch = Partial<
  Pick<Suivi, 'status' | 'noRelance' | 'relanceAt' | 'dueAt' | 'replyReceived' | 'responsible' | 'relanceCount'>
>

/** Contrat avec l'API Symfony : GET/POST /api/suivis, PATCH /api/suivis/{id}, POST /api/suivis/{id}/close. */
export interface SuivisApi {
  list(): Promise<Suivi[]>
  create(n: NewSuivi): Promise<Suivi>
  update(id: string, patch: SuiviPatch, journal?: JournalEntry): Promise<Suivi>
  close(id: string): Promise<void>
}

export interface MailApi {
  list(): Promise<Mail[]>
  /** Corps en texte brut, lu à la demande (jamais de HTML). */
  body(id: string): Promise<string[]>
  markRead(id: string): Promise<void>
  setClaim(id: string, claim: Claim, entry: JournalEntry, composing?: string | null): Promise<Mail>
  addNote(id: string, note: Note): Promise<Mail>
  linkSuivi(id: string, suiviId: string): Promise<void>
  send(payload: { to: string; subject: string; body: string; follow: boolean }): Promise<void>
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

export interface SystemApi {
  notifications(): Promise<AppNotification[]>
  markAllRead(): Promise<void>
  connections(): Promise<Connection[]>
  retryConnection(id: Connection['id']): Promise<Connection>
  log(): Promise<LogEntry[]>
}

export interface Api {
  suivis: SuivisApi
  mails: MailApi
  tasks: TasksApi
  agenda: AgendaApi
  system: SystemApi
}
