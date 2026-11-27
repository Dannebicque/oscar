import type {
  AdminUser, AuditEntry, HealthReport, InstanceConfig, InstanceInfo, Identity, IdentityInput, FolderInfo, MoveTarget, AppNotification, Attachment, CalEvent, InvitationResponse, MailInvitation, CalendarAccount, CalendarSource, ClaimAction, Connection, JournalEntry, LogEntry, Mail, MailAccount,
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
  /** Compte et identité d'expéditeur ; absents = premier compte, identité par défaut. */
  accountId?: string
  identityId?: string
  /** Délai de relance en heures ; absent = ne pas suivre. */
  followHours?: number
  followSuggestedByAi?: boolean
  inReplyToMailId?: string
  /** Pièces jointes (5 Mo au total), contenu en base64. */
  attachments?: { name: string; type: string; content: string }[]
}

/** Mail programmé (F-73) : écrit, et envoyé à l'heure choisie par l'utilisateur. */
export interface ScheduledMail { id: string; subject: string; to: string[]; sendAt: string; status: 'pending' | 'sending' | 'failed'; attachments: number }

export interface MailBody { paragraphs: string[]; html: string | null; blockedImages: number }

export interface MailApi {
  /** Programme l'envoi à l'heure choisie (clic explicite) ; rien ne part avant. */
  schedule(p: SendPayload, sendAt: string): Promise<ScheduledMail>
  scheduled(): Promise<ScheduledMail[]>
  /** Annule un mail programmé et rend le message pour le rouvrir dans l'éditeur. */
  cancelScheduled(id: string): Promise<SendPayload>
  list(): Promise<Mail[]>
  /** Corps lu à la demande : le texte, et la mise en forme assainie s'il y en a une. `images` : garder les images distantes. */
  body(id: string, images?: boolean): Promise<MailBody>
  /** Suggestions de l'IA pour ce mail (tâche, événement, créneaux) ; vide si l'IA est coupée. */
  hints(id: string): Promise<MailHints>
  /** Brouillon de réponse préparé par Oscar (jamais envoyé) ; `slots` : créneaux choisis, déjà formatés. */
  replyDraft(id: string, p: { tone: 'warm' | 'short'; slots: string[] }): Promise<string>
  /** Pièces jointes d'un mail : métadonnées seulement, lues à la demande. */
  attachments(id: string): Promise<Attachment[]>
  /** Télécharge une pièce jointe (jamais affichée dans l'application). */
  download(id: string, a: Attachment): Promise<void>
  /** Dépose un brouillon dans le dossier Drafts du serveur (rien n'est envoyé) ; `replaces` : l'ancien brouillon part à la corbeille. */
  saveDraft(p: Omit<SendPayload, 'followHours' | 'followSuggestedByAi'> & { replaces?: string }): Promise<void>
  /** Copies (Cc, Cci) d'un brouillon : la liste des mails ne les garde pas. */
  draftFields(id: string): Promise<{ cc: string[]; bcc: string[] }>
  /** Une pièce jointe sous forme de fichier, pour la remettre dans l'éditeur (reprise d'un brouillon). */
  attachmentFile(id: string, a: Attachment): Promise<File>
  /** Invitation d'agenda jointe au mail (F-25) et réponse déjà donnée. Lire ne change rien. */
  invitation(id: string): Promise<{ invitation: MailInvitation | null; response: InvitationResponse | null }>
  /** Répond à l'invitation (un clic de l'utilisateur) : la réponse part à l'organisateur ; accepté ou peut-être : l'événement entre dans l'agenda choisi. */
  replyInvitation(id: string, p: { response: InvitationResponse; calendarId?: string }): Promise<{ added: boolean }>
  /** Suggestion de tâche à partir du texte en cours de rédaction (F-73) : à valider, rien n'est créé sans clic. */
  composeHints(subject: string, body: string): Promise<{ task: { title: string; dueAt: string; quote: string } | null }>
  markRead(id: string): Promise<void>
  /** Archiver, corbeille, retour en boîte de réception : le serveur d'abord, jamais de suppression définitive. */
  move(id: string, folder: MoveTarget): Promise<void>
  /** Dossiers et sous-dossiers de la messagerie (ceux du serveur IMAP). Un dossier ne se supprime que vide. */
  folders: {
    list(): Promise<FolderInfo[]>
    create(name: string, parent?: string): Promise<FolderInfo>
    rename(path: string, name: string): Promise<{ path: string; name: string }>
    remove(path: string): Promise<void>
    /** Ouvrir un dossier : récupère ses derniers messages (100 au plus à la première visite). Renvoie le nombre de nouveaux messages. */
    open(path: string): Promise<number>
  }
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
  remove(id: string): Promise<void>
}

export interface AgendaApi {
  sources(): Promise<CalendarSource[]>
  events(): Promise<CalEvent[]>
  create(e: Omit<CalEvent, 'id'>): Promise<CalEvent>
  /** Créneaux OCCUPÉS des collègues d'une boîte partagée qui ont accepté le partage (F-26) : jamais un titre ni un lieu. */
  teamBusy(mailbox: string, from: Date, to: Date): Promise<{ member: string; start: string; end: string }[]>
}

export interface AccountsApi {
  mail: {
    list(): Promise<MailAccount[]>
    create(a: NewMailAccount): Promise<MailAccount>
    update(id: string, patch: Partial<Pick<MailAccount, 'relanceHours' | 'businessDays' | 'aiAllowed' | 'label' | 'signature' | 'attachmentLimitMb'>>): Promise<MailAccount>
    remove(id: string): Promise<void>
    test(id: string): Promise<{ ok: boolean }>
  }
  calendar: {
    list(): Promise<CalendarAccount[]>
    create(a: NewCalendarAccount): Promise<CalendarAccount>
    remove(id: string): Promise<void>
  }
  shared: {
    list(): Promise<SharedMailboxInfo[]>
    /** Nom et signature de l'équipe pour les réponses « De : contact@ » (administrateur de la boîte). */
    updateSender(id: string, p: { senderName: string | null; signature: string | null }): Promise<SharedMailboxInfo>
    /** F-40 : créer une boîte (on en devient administrateur). L'identifiant court se déduit de l'adresse. */
    create(p: { address: string; name: string }): Promise<SharedMailboxInfo>
    update(id: string, p: { autoFollow?: boolean; aiEnabled?: boolean }): Promise<SharedMailboxInfo>
    /** Ajoute une personne (identifiant ou adresse de son compte) ou change son rôle. */
    addMember(id: string, username: string, role: 'member' | 'admin'): Promise<SharedMailboxInfo>
    removeMember(id: string, username: string): Promise<void>
    /** Accepter une invitation (la refuser = se retirer). */
    accept(id: string): Promise<SharedMailboxInfo>
    /** Possible seulement tant que la boîte n'a aucune demande. */
    remove(id: string): Promise<void>
  }
  /** Identités d'expéditeur et signatures. La principale de chaque compte existe d'office. */
  identities: {
    list(): Promise<Identity[]>
    create(i: IdentityInput & { accountId: string }): Promise<Identity>
    update(id: string, i: IdentityInput): Promise<Identity>
    setDefault(id: string): Promise<Identity>
    remove(id: string): Promise<void>
    /** Aperçu d'une signature HTML : ce qui sera réellement envoyé (assaini par le serveur) et son équivalent texte. */
    preview(html: string): Promise<{ html: string; text: string }>
  }
}

/** Réglages utiles au serveur (relances, récaps, mode d'IA). Thème et densité restent sur l'appareil. */
export type ServerSettings = Record<string, string | number | boolean | Record<string, Record<string, boolean>>>

export interface MessageTemplate { id: string; name: string; body: string }

export interface SortRule { id: string; field: 'sender' | 'domain' | 'subject'; value: string; action: 'archive' | 'trash'; enabled: boolean }

export interface FollowRule { id: string; kind: 'sender' | 'domain'; value: string; enabled: boolean }

export interface AiConfig { kind: 'openai' | 'anthropic'; baseUrl: string; model: string; hasKey: boolean; configured: boolean }
export interface AiConfigInput { kind: 'openai' | 'anthropic'; baseUrl: string; model: string; /** absent : on garde la clé ; chaîne vide : on la retire. */ apiKey?: string }

export interface SettingsApi {
  sortRules: {
    list(): Promise<SortRule[]>
    create(field: SortRule['field'], value: string, action: SortRule['action']): Promise<SortRule>
    setEnabled(id: string, enabled: boolean): Promise<SortRule>
    remove(id: string): Promise<void>
  }
  templates: {
    list(): Promise<MessageTemplate[]>
    create(name: string, body: string): Promise<MessageTemplate>
    update(id: string, name: string, body: string): Promise<MessageTemplate>
    remove(id: string): Promise<void>
  }
  followRules: {
    list(): Promise<FollowRule[]>
    create(kind: FollowRule['kind'], value: string): Promise<FollowRule>
    setEnabled(id: string, enabled: boolean): Promise<FollowRule>
    remove(id: string): Promise<void>
  }
  aiConfig(): Promise<AiConfig>
  saveAiConfig(c: AiConfigInput): Promise<AiConfig>
  testAiConfig(): Promise<{ ok: boolean; message: string }>
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

export interface AuthConfig { password: boolean; registration: boolean; cas: boolean; dev: boolean }
export interface ProbeInput { imapHost: string; imapPort: number; imapEncryption: string; smtpHost: string; smtpPort: number; smtpEncryption: string; username: string; password: string }
export interface ProbeResult { imap: { ok: boolean; message: string }; smtp: { ok: boolean; message: string } }

export interface AuthApi {
  /** Ce que la page de connexion propose (comptes locaux, inscription, CAS, connexion de dev). */
  config(): Promise<AuthConfig>
  login(email: string, password: string): Promise<string>
  register(name: string, email: string, password: string): Promise<string>
  /** Demande un lien de réinitialisation. Même réponse que l'adresse ait un compte ou non ; `devLink` seulement en développement. */
  forgot(email: string): Promise<{ devLink?: string }>
  reset(token: string, password: string): Promise<void>
  /** Teste les serveurs IMAP et SMTP avant d'enregistrer le compte. */
  probe(p: ProbeInput): Promise<ProbeResult>
  devLogin(username: string): Promise<string>
  exchange(code: string): Promise<string>
  me(): Promise<{ username: string; displayName: string; email?: string | null; hasPassword?: boolean; admin?: boolean }>
  changePassword(current: string, password: string): Promise<void>
  deleteAccount(secret: { password?: string; confirm?: string }): Promise<void>
  logout(): Promise<void>
}

export interface DirectoryApi {
  /** L'annuaire de l'établissement (LDAP) est-il configuré sur ce serveur ? */
  status(): Promise<{ configured: boolean }>
  search(q: string): Promise<{ name: string; address: string; detail?: string | null }[]>
}

export interface SetupPayload {
  code: string
  name: string
  email: string
  password: string
  config: { instanceName: string; registration: 'open' | 'closed'; supportEmail: string }
}

export interface InstanceApi {
  /** Public : de quoi afficher l'écran de connexion et savoir si l'assistant d'installation doit s'ouvrir. */
  info(): Promise<InstanceInfo>
  /** Assistant de premier lancement : crée l'administrateur (code d'installation exigé). Renvoie un jeton de session. */
  setup(p: SetupPayload): Promise<string>
  /** Administrateur seulement. */
  config(): Promise<InstanceConfig>
  updateConfig(patch: Partial<InstanceConfig>): Promise<InstanceConfig>
  /** Comptes (administrateur seulement) : jamais de contenu. */
  users(): Promise<AdminUser[]>
  /** Crée un compte local ; le lien pour choisir un mot de passe part par e-mail à l'invité, jamais à l'administrateur. */
  invite(name: string, email: string): Promise<{ emailSent: boolean }>
  setDisabled(id: string, disabled: boolean): Promise<void>
  /** Envoie à l'utilisateur seul un lien de réinitialisation (comptes locaux). */
  resetLink(id: string): Promise<{ emailSent: boolean }>
  setAdmin(id: string, admin: boolean): Promise<void>
  /** Suppression définitive du compte et de ses données ; `confirm` = l'adresse du compte. */
  deleteUser(id: string, confirm: string): Promise<void>
  health(): Promise<HealthReport>
  audit(): Promise<AuditEntry[]>
}

export interface Api {
  instance: InstanceApi
  directory: DirectoryApi
  suivis: SuivisApi
  mails: MailApi
  tasks: TasksApi
  agenda: AgendaApi
  accounts: AccountsApi
  settings: SettingsApi
  system: SystemApi
  auth: AuthApi
}
