import { addDays, atTime, isoDay } from '../domain/dates'
import { handle as claimHandle, release as claimRelease, reopen as claimReopen, take as claimTake, takeOver as claimTakeOver } from '../domain/claim'
import { sanitizeSignature, signatureText } from '../domain/signatureHtml'
import { ApiError, type Api, type ScheduledMail, type SendPayload, type ServerSettings } from './types'
import type {
  InstanceConfig, Identity,
  AppNotification, CalEvent, CalendarAccount, CalendarSource, Connection, LogEntry, Mail, MailAccount, Suivi, Task,
} from '../domain/types'

export const ME = 'David'
export const SHARED = { id: 'contact', address: 'contact@iut-troyes.fr', members: ['Claire', 'Marc', 'Léa', ME] }

/** Copie profonde tolérante : les objets réactifs de Vue (proxys) ne passent pas par structuredClone. */
const clone = <T>(v: T): T => JSON.parse(JSON.stringify(v)) as T
const uid = (p: string) => `${p}${Math.random().toString(36).slice(2, 8)}`

export function createMockApi(now: Date = new Date()): Api {
  const day = (n: number) => isoDay(addDays(now, n))
  const iso = (n: number, h: number, m = 0) => atTime(addDays(now, n), h, m).toISOString()
  const hoursAgo = (h: number) => new Date(now.getTime() - h * 3_600_000).toISOString()
  const entry = (h: number, author: string, action: string) => ({ at: hoursAgo(h), author, action })

  const base = (id: string, p: Partial<Suivi>): Suivi => ({
    id, subject: '', contact: '', direction: 'waiting', status: 'waiting', dueAt: day(7), relanceAt: null, relanceCount: 0,
    noRelance: false, responsible: ME, mailbox: 'personal', mailId: null, replyReceived: false, suggestedByAi: false,
    reason: null, journal: [], ...p,
  })

  let suivis: Suivi[] = [
    base('s1', { subject: 'Planning des soutenances', contact: 'Sophie Laurent', direction: 'toHandle', status: 'new', dueAt: day(2), replyReceived: true, mailId: 'm1', reason: 'Sophie a répondu : il reste une action de ta part.', journal: [entry(30, ME, 'created'), entry(2, 'Sophie Laurent', 'replyReceived')] }),
    base('s2', { subject: 'Devis des affiches', contact: 'Marc Dubois', status: 'waiting', dueAt: day(3), relanceAt: day(-1), relanceCount: 1, mailId: 'm2', suggestedByAi: true, reason: "Marc n'a pas donné signe de vie depuis 2 jours.", journal: [entry(52, ME, 'created'), entry(52, ME, 'sent')] }),
    base('s3', { subject: 'Salle B104 pour le jury', contact: 'Scolarité', direction: 'toHandle', status: 'inProgress', dueAt: day(-3), mailId: 'm3', reason: 'Échéance dépassée depuis 3 jours.', journal: [entry(96, ME, 'created')] }),
    base('s4', { subject: 'Convention de stage', contact: 'Claire Petit', status: 'waiting', dueAt: day(6), relanceAt: day(3), mailId: 'm4', journal: [entry(20, ME, 'created')] }),
    base('s5', { subject: 'Matériel de TP', contact: 'Service achats', status: 'done', dueAt: day(-8), journal: [entry(200, ME, 'created'), entry(150, ME, 'closed')] }),
    // Historique pour le bilan : des demandes déjà traitées, avec le délai de réponse de chacun (jamais affichées comme à faire).
    base('h1', { subject: 'Convention 2025 : signature', contact: 'Claire Petit', status: 'done', dueAt: day(-30), relanceCount: 1, journal: [entry(24 * 40, ME, 'sent'), entry(24 * 38, 'Claire Petit', 'replyReceived'), entry(24 * 37, ME, 'closed')] }),
    base('h2', { subject: 'Réservation de la salle B12', contact: 'Scolarité', status: 'done', dueAt: day(-20), journal: [entry(24 * 25, ME, 'sent'), entry(24 * 25 - 5, 'Scolarité', 'replyReceived'), entry(24 * 24, ME, 'closed')] }),
    base('h3', { subject: 'Devis des affiches : version 1', contact: 'Marc Dubois', status: 'done', dueAt: day(-45), relanceCount: 2, journal: [entry(24 * 60, ME, 'sent'), entry(24 * 54, 'Marc Dubois', 'replyReceived'), entry(24 * 53, ME, 'closed')] }),
    base('h4', { subject: 'Programme des soutenances', contact: 'Sophie Laurent', status: 'done', dueAt: day(-10), journal: [entry(24 * 14, ME, 'sent'), entry(24 * 14 - 3, 'Sophie Laurent', 'replyReceived'), entry(24 * 13, ME, 'closed')] }),
    base('h5', { subject: 'Question sur le stage', contact: 'Service achats', status: 'waiting', dueAt: day(-5), relanceCount: 1, relanceAt: day(1), journal: [entry(24 * 12, ME, 'sent')] }),
    base('h6', { subject: 'Relance facture TP', contact: 'Service achats', status: 'waiting', dueAt: day(-2), journal: [entry(24 * 9, ME, 'sent')] }),
    base('s6', { subject: 'Question d’inscription', contact: 'Camille Roux', direction: 'toHandle', status: 'inProgress', mailbox: 'contact', responsible: null, dueAt: day(1), mailId: 'c1', journal: [entry(5, 'Claire', 'take')] }),
    base('s7', { subject: 'Demande de visite de l’IUT', contact: 'Lycée Camus', direction: 'toHandle', status: 'new', mailbox: 'contact', responsible: null, dueAt: day(4), mailId: 'c2', suggestedByAi: true, reason: 'Arrivé sur contact@ depuis 28 h, personne ne l’a pris.' }),
  ]

  const m = (id: string, p: Partial<Mail>): Mail => ({
    id, mailbox: 'personal', folder: 'inbox', from: { name: '', address: '' }, to: [{ name: ME, address: 'david@iut-troyes.fr' }],
    subject: '', paragraphs: [], at: hoursAgo(3), unread: false, attachments: [], suivi: null, claim: null, composing: null,
    notes: [], activity: [], hints: {}, ...p,
  })
  const claim = (state: 'new' | 'taken' | 'handled', by: string | null = null) => ({ state, by })

  let mockFolders: { path: string; name: string; delimiter: string; system: boolean; count: number }[] = [
    { path: 'Projets', name: 'Projets', delimiter: '/', system: false, count: 0 },
    { path: 'Projets/Jury de janvier', name: 'Jury de janvier', delimiter: '/', system: false, count: 0 },
    { path: 'Scolarité', name: 'Scolarité', delimiter: '/', system: false, count: 0 },
  ]
  const drafts = new Map<string, { cc: string[]; bcc: string[]; files: { name: string; type: string; content: string }[] }>()
  let sharedSender: { senderName: string | null; signature: string | null } = { senderName: 'Équipe Contact IUT', signature: '-- \nL’équipe contact\nIUT de Troyes' }
  const sharedInfo = () => ({
    id: 'sm1', slug: SHARED.id, address: SHARED.address, name: 'Contact', role: 'admin' as const, aiEnabled: false, identityId: 'shared:sm1', ...sharedSender,
    members: SHARED.members.map((n) => ({ username: n.toLowerCase(), displayName: n, role: n === ME ? ('admin' as const) : ('member' as const) })),
  })
  let instanceConfig: InstanceConfig = { instanceName: 'Oscar', registration: 'open', supportEmail: '', legalUrl: '', privacyUrl: '', defaultAttachmentMb: 10, aiExternalAllowed: true, setupDone: true }
  let scheduledMails: { row: ScheduledMail; payload: SendPayload }[] = []
  let mails: Mail[] = [
    m('m1', { from: { name: 'Sophie Laurent', address: 'sophie.laurent@univ-reims.fr' }, subject: 'Re : Planning des soutenances', at: hoursAgo(2), unread: true, suivi: 's1', paragraphs: ['Bonjour David,', 'Merci pour le planning. Peux-tu confirmer le nombre de participants avant mardi ? Nous préparons le jury jeudi à 10h.', 'Bien cordialement,', 'Sophie'], hints: { summary: 'Sophie demande de confirmer le nombre de participants avant mardi. Le jury se prépare jeudi à 10h.', event: { title: 'Préparation du jury', start: iso(6, 10), end: iso(6, 11), quote: 'jeudi à 10h' }, task: { title: 'Confirmer le nombre de participants', dueAt: day(4), quote: 'avant mardi' } } }),
    m('m5', { from: { name: 'Claire Roux', address: 'claire.roux@iut-troyes.fr' }, subject: 'Disponibilités pour la réunion pédagogique', at: hoursAgo(5), unread: true, paragraphs: ['Bonjour,', 'Pourrais-tu m’indiquer tes disponibilités la semaine du 30 novembre pour une réunion d’une heure ?', 'Merci !', 'Claire'], hints: { summary: 'Claire demande tes disponibilités la semaine du 30 novembre pour une réunion d’une heure.', availability: { slots: [0, 1, 2, 3, 4].map((i) => ({ start: iso(10 + i, 9 + i), end: iso(10 + i, 10 + i) })) } } }),
    m('m9', { from: { name: 'Claire Petit', address: 'claire.petit@entreprise.fr' }, subject: 'Invitation : Soutenance de stage de M. Lemoine', at: hoursAgo(4), unread: true, paragraphs: ['Bonjour David,', 'Je t’invite à la soutenance de stage de M. Lemoine.', 'Claire'], invitation: { uid: 'soutenance-lemoine@iut', title: 'Soutenance de stage : M. Lemoine', start: iso(3, 14), end: iso(3, 15), location: 'Salle B12', organizerName: 'Claire Petit', organizerAddress: 'claire.petit@entreprise.fr' } }),
    m('m2', { folder: 'sent', from: { name: ME, address: 'david@iut-troyes.fr' }, to: [{ name: 'Marc Dubois', address: 'marc.dubois@imprimerie.fr' }], subject: 'Devis des affiches', at: hoursAgo(52), suivi: 's2', paragraphs: ['Bonjour Marc,', 'Pourriez-vous m’envoyer le devis pour 40 affiches A2 ?', 'Merci, David'] }),
    m('m3', { from: { name: 'Scolarité', address: 'scolarite@iut-troyes.fr' }, subject: 'Salle B104 pour le jury', at: hoursAgo(96), suivi: 's3', paragraphs: ['Bonjour, la salle B104 est disponible sous réserve de confirmation avant vendredi.'] }),
    m('m4', { folder: 'sent', from: { name: ME, address: 'david@iut-troyes.fr' }, to: [{ name: 'Claire Petit', address: 'claire.petit@entreprise.fr' }], subject: 'Convention de stage', at: hoursAgo(20), suivi: 's4', paragraphs: ['Bonjour Claire, ci-joint la convention à signer.'], attachments: [{ name: 'convention.pdf', size: '184 Ko' }] }),
    m('m6', { from: { name: 'Service achats', address: 'achats@iut-troyes.fr' }, to: [{ name: 'Direction des études', address: 'etudes@iut-troyes.fr' }], subject: 'Matériel de TP livré', at: hoursAgo(160), paragraphs: ['Le matériel est arrivé au magasin.'] }),
    m('c1', { mailbox: 'contact', from: { name: 'Camille Roux', address: 'camille.roux@gmail.com' }, to: [{ name: 'contact@', address: SHARED.address }], subject: 'Question d’inscription', at: hoursAgo(5), unread: true, suivi: 's6', claim: claim('taken', 'Claire'), composing: 'Claire', paragraphs: ['Bonjour, les inscriptions sont-elles encore ouvertes pour la rentrée de janvier ?', 'Merci d’avance.'], activity: [entry(5, 'Claire', 'take')] }),
    m('c2', { mailbox: 'contact', from: { name: 'Lycée Camus', address: 'secretariat@lycee-camus.fr' }, to: [{ name: 'contact@', address: SHARED.address }], subject: 'Demande de visite de l’IUT', at: hoursAgo(28), unread: true, suivi: 's7', claim: claim('new'), paragraphs: ['Bonjour, une classe de terminale souhaiterait visiter l’IUT en décembre.'] }),
    m('c3', { mailbox: 'contact', from: { name: 'Imprimerie du Nord', address: 'devis@imprimerie-nord.fr' }, to: [{ name: 'contact@', address: SHARED.address }], subject: 'Catalogue 2027', at: hoursAgo(70), claim: claim('handled', 'Marc'), paragraphs: ['Veuillez trouver notre catalogue.'], activity: [entry(70, 'Marc', 'take'), entry(69, 'Marc', 'handled')], notes: [{ id: 'n1', author: 'Marc', text: 'Rien à faire, archivé.', at: hoursAgo(69) }] }),
  ]

  let tasks: Task[] = [
    { id: 't1', title: 'Réserver les salles de soutenance', description: '', dueAt: day(7), priority: 2, project: 'Jury de janvier', tags: ['salles'], done: false, origin: 'manual', toConfirm: false, source: null },
    { id: 't2', title: 'Relire la convention de Claire Petit', description: '', dueAt: day(0), priority: 1, project: 'Stages', tags: ['juridique'], done: false, origin: 'mail', toConfirm: false, source: 'Convention de stage' },
    { id: 't3', title: 'Confirmer le nombre de participants', description: 'Avant mardi, date à vérifier.', dueAt: day(4), priority: 2, project: 'Jury de janvier', tags: [], done: false, origin: 'ai', toConfirm: true, source: 'Re : Planning des soutenances' },
    { id: 't4', title: 'Préparer le support de la réunion pédagogique', description: '', dueAt: day(-1), priority: 3, project: 'Pédagogie', tags: ['réunion'], done: false, origin: 'event', toConfirm: false, source: 'Réunion pédagogique' },
    { id: 't5', title: 'Commander le papier A2', description: '', dueAt: null, priority: 4, project: null, tags: [], done: false, origin: 'manual', toConfirm: false, source: null },
    { id: 't6', title: 'Valider le budget TP', description: '', dueAt: day(-6), priority: 2, project: 'Pédagogie', tags: [], done: true, origin: 'manual', toConfirm: false, source: null },
  ]

  const sources: CalendarSource[] = [
    { id: 'iut', label: 'IUT', kind: 'caldav', personal: false },
    { id: 'perso', label: 'Perso', kind: 'google', personal: true },
  ]
  let events: CalEvent[] = [
    { id: 'e1', calendar: 'iut', title: 'Point équipe', start: iso(0, 9), end: iso(0, 9, 45), location: 'Salle B12', attendees: [{ name: 'Sophie Laurent', address: 'sophie.laurent@univ-reims.fr' }] },
    { id: 'e2', calendar: 'iut', title: 'Soutenance de stage : M. Lemoine', start: iso(0, 14), end: iso(0, 15), location: 'Salle B104', attendees: [{ name: 'Claire Petit', address: 'claire.petit@entreprise.fr' }] },
    { id: 'e3', calendar: 'perso', title: 'Dentiste', start: iso(0, 17), end: iso(0, 18), location: null },
    { id: 'e4', calendar: 'iut', title: 'Réunion pédagogique', start: iso(1, 10), end: iso(1, 11, 30), location: 'Visio', attendees: [{ name: 'Claire Roux', address: 'claire.roux@iut-troyes.fr' }] },
    { id: 'e5', calendar: 'iut', title: 'Conseil de département', start: iso(2, 14), end: iso(2, 16), location: 'Amphi 2' },
    { id: 'e6', calendar: 'iut', title: 'Jury de stage', start: iso(4, 9), end: iso(4, 12), location: 'Salle B104' },
  ]

  let notifications: AppNotification[] = [
    { id: 'n1', kind: 'replyReceived', params: { who: 'Sophie Laurent', subject: 'Planning des soutenances' }, at: hoursAgo(2), read: false, to: 'suivis' },
    { id: 'n2', kind: 'relanceDue', params: { who: 'Marc Dubois', subject: 'Devis des affiches', n: 1 }, at: hoursAgo(4), read: false, to: 'suivis' },
    { id: 'n3', kind: 'connectionFailed', params: { account: 'Google Agenda' }, at: hoursAgo(9), read: false, to: 'journal' },
    { id: 'n4', kind: 'invitation', params: { title: 'Conseil de département' }, at: hoursAgo(30), read: true, to: 'agenda' },
  ]
  let connections: Connection[] = [
    { id: 'mail', state: 'ok', detail: 'david@iut-troyes.fr', lastOk: hoursAgo(0.1) },
    { id: 'calendar', state: 'ok', detail: 'IUT · CalDAV', lastOk: hoursAgo(0.1) },
    { id: 'google', state: 'error', detail: 'Perso · Google Agenda', lastOk: hoursAgo(9) },
    { id: 'ai', state: 'ok', detail: 'ai.university', lastOk: hoursAgo(0.1) },
  ]
  const log: LogEntry[] = [
    { id: 'l1', at: hoursAgo(9), account: 'Google Agenda', type: 'auth', message: 'authRefused', action: 'reconnect' },
    { id: 'l2', at: hoursAgo(26), account: 'Messagerie', type: 'sync', message: 'timeout', action: 'none' },
  ]

  const mailAccounts: MailAccount[] = [{ id: 'acc1', label: 'david@iut-troyes.fr', address: 'david@iut-troyes.fr', imapHost: 'imap.iut-troyes.fr', imapPort: 993, imapEncryption: 'ssl', smtpHost: 'smtp.iut-troyes.fr', smtpPort: 587, smtpEncryption: 'tls', username: 'david', aiAllowed: true }]
  /** Signature en texte ou en HTML (assaini) : le texte équivalent est dérivé du HTML. */
  const sig = (i: { signature: string | null; signatureHtml?: string | null }) => {
    if (i.signatureHtml?.trim()) { const safe = sanitizeSignature(i.signatureHtml); return { signatureHtml: safe, signature: signatureText(safe) || null } }
    return { signatureHtml: null, signature: i.signature || null }
  }
  let identities: Identity[] = [
    { id: 'id1', accountId: 'acc1', name: 'David Annebicque', address: 'david@iut-troyes.fr', replyTo: null, signature: '-- \nDavid Annebicque\nIUT de Troyes · Département informatique', isDefault: true, primary: true },
    { id: 'id2', accountId: 'acc1', name: 'Direction des études', address: 'etudes@iut-troyes.fr', replyTo: 'secretariat@iut-troyes.fr', signature: '-- \nLa direction des études\nIUT de Troyes', isDefault: false, primary: false },
  ]
  const calendarAccounts: CalendarAccount[] = [
    { id: 'iut', label: 'IUT', kind: 'caldav', url: 'https://agenda.iut-troyes.fr/dav/', username: 'david', personal: false },
    { id: 'perso', label: 'Perso', kind: 'google', url: '', username: '', personal: true },
  ]
  const sortRules: import('./types').SortRule[] = [{ id: 'sort1', field: 'domain', value: 'newsletter.example', action: 'archive', enabled: true }]
  const templates: import('./types').MessageTemplate[] = [{ id: 'tpl1', name: 'Accusé de réception', body: 'Bonjour {prenom},\n\nBien reçu, merci. Je reviens vers toi rapidement.' }]
  const rules: import('./types').FollowRule[] = [{ id: 'rule1', kind: 'domain', value: 'univ-reims.fr', enabled: true }]
  let aiCfg: import('./types').AiConfig = { kind: 'openai', baseUrl: '', model: '', hasKey: false, configured: false }
  let serverSettings: ServerSettings = {}
  const findMail = (id: string) => mails.find((x) => x.id === id)
  const replaceMail = (m: Mail) => { mails = mails.map((x) => (x.id === m.id ? m : x)) }

  return {
    suivis: {
      async list() { return clone(suivis) },
      async create(n) {
        const s = base(uid('s'), { ...n, status: 'new', journal: [{ at: now.toISOString(), author: ME, action: 'created' }] })
        suivis = [...suivis, s]
        return clone(s)
      },
      async update(id, patch, journal) {
        suivis = suivis.map((s) => (s.id === id ? { ...s, ...patch, journal: journal ? [...s.journal, journal] : s.journal } : s))
        return clone(suivis.find((s) => s.id === id)!)
      },
      async close(id) {
        suivis = suivis.map((s) => (s.id === id ? { ...s, status: 'done', replyReceived: false, journal: [...s.journal, { at: new Date().toISOString(), author: ME, action: 'closed' }] } : s))
      },
      async draftRelance() { return null },
    },
    mails: {
      async list() { return clone(mails) },
      async body(id) { return clone(findMail(id)?.paragraphs ?? []) },
      async hints(id) { return clone(findMail(id)?.hints ?? {}) },
      async replyDraft(id, p) {
        const first = (findMail(id)?.from.name ?? '').split(' ')[0]
        const list = p.slots.map((l) => `- ${l}`).join('\n')
        return p.tone === 'short'
          ? `Bonjour ${first},\n\n${list ? `Disponible :\n${list}` : 'Bien reçu, je reviens vers toi vite.'}\n\nDavid`
          : `Bonjour ${first},\n\n${list ? `Voici mes disponibilités :\n${list}\n\nDis-moi ce qui te convient, je confirme aussitôt.` : 'Merci pour ton message. Je regarde cela et je reviens vers toi rapidement.'}\n\nCordialement,\nDavid`
      },
      async move(id, folder) { mails = mails.map((x) => (x.id === id ? { ...x, folder } : x)) },
      folders: {
        async list() { return clone(mockFolders) },
        async create(name, parent) {
          const path = parent ? `${parent}/${name}` : name
          if (mockFolders.some((f) => f.path.toLowerCase() === path.toLowerCase())) throw new ApiError(409, 'Un dossier porte déjà ce nom.')
          const f = { path, name, delimiter: '/', system: false, count: 0 }
          mockFolders = [...mockFolders, f]
          return clone(f)
        },
        async rename(path, name) {
          const next = `${path.split('/').slice(0, -1).concat(name).join('/')}`
          mockFolders = mockFolders.map((f) => (f.path === path || f.path.startsWith(`${path}/`) ? { ...f, path: next + f.path.slice(path.length), name: f.path === path ? name : f.name } : f))
          mails = mails.map((x) => (x.folder === `f:${path}` || x.folder.startsWith(`f:${path}/`) ? { ...x, folder: `f:${next}${x.folder.slice(2 + path.length)}` as typeof x.folder } : x))
          return { path: next, name }
        },
        async remove(path) {
          if (mails.some((x) => x.folder === `f:${path}`)) throw new ApiError(409, 'Ce dossier n’est pas vide. Déplace ses messages ailleurs avant de le supprimer.')
          if (mockFolders.some((f) => f.path.startsWith(`${path}/`))) throw new ApiError(409, 'Ce dossier contient des sous-dossiers. Vide-les ou supprime-les d’abord.')
          mockFolders = mockFolders.filter((f) => f.path !== path)
        },
        async open() { return 0 },
      },
      async attachments(id) { return clone(findMail(id)?.attachments ?? []).map((a, index) => ({ ...a, index })) },
      async download() { /* démo : rien à télécharger */ },
      async schedule(p, sendAt) {
        const row = { id: `s${Date.now()}`, subject: p.subject, to: p.to, sendAt, status: 'pending' as const, attachments: p.attachments?.length ?? 0 }
        scheduledMails = [...scheduledMails, { row, payload: clone(p) }]
        return row
      },
      async scheduled() { return scheduledMails.map((x) => x.row) },
      async cancelScheduled(id) {
        const x = scheduledMails.find((y) => y.row.id === id)
        if (!x) throw new ApiError(404, 'Mail programmé introuvable.')
        scheduledMails = scheduledMails.filter((y) => y !== x)
        return x.payload
      },
      async draftFields(id) { const d = drafts.get(id); return { cc: d?.cc ?? [], bcc: d?.bcc ?? [] } },
      async attachmentFile(id, a) {
        const c = drafts.get(id)?.files.find((f) => f.name === a.name)
        if (!c) throw new ApiError(404, 'download')
        return new File([Uint8Array.from(atob(c.content), (ch) => ch.charCodeAt(0))], c.name, { type: c.type })
      },
      async saveDraft(p) {
        const draftId = `d${Date.now()}`
        drafts.delete(p.replaces ?? '')
        drafts.set(draftId, { cc: p.cc, bcc: p.bcc, files: p.attachments ?? [] })
        mails = [...mails.filter((x) => x.id !== p.replaces), m(draftId, { attachments: (p.attachments ?? []).map((a, index) => ({ name: a.name, size: `${Math.max(1, Math.round((a.content.length * 0.75) / 1024))} Ko`, index })), folder: 'drafts', from: { name: ME, address: 'david@iut-troyes.fr' }, to: p.to.map((a) => ({ name: a, address: a })), subject: p.subject, at: new Date().toISOString(), paragraphs: p.body.split(/\n{2,}/).filter(Boolean) })]
      },
      async invitation(id) { const m = findMail(id); return { invitation: clone(m?.invitation ?? null), response: m?.invitationResponse ?? null } },
      async replyInvitation(id, p) {
        const m = findMail(id)
        if (!m?.invitation) throw new ApiError(422, 'Ce message ne contient pas d’invitation que je sache lire.')
        mails = mails.map((x) => (x.id === id ? { ...x, invitationResponse: p.response } : x))
        let added = false
        if (p.response !== 'declined' && p.calendarId && !events.some((e) => e.id === m.invitation!.uid)) {
          events.push({ id: m.invitation.uid, calendar: p.calendarId, title: m.invitation.title, start: m.invitation.start, end: m.invitation.end, location: m.invitation.location })
          added = true
        }
        return { added }
      },
      async composeHints(_subject, body) {
        const m = body.match(/(avant|d['’]ici|pour)\s+(lundi|mardi|mercredi|jeudi|vendredi)/i)
        if (!m || body.trim().length < 15) return { task: null }
        const days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']
        const d = new Date(); d.setHours(0, 0, 0, 0)
        do d.setDate(d.getDate() + 1); while (d.getDay() !== days.indexOf(m[2].toLowerCase()))
        const first = body.split(/[.?!\n]/).find((x) => /avant|d['’]ici|pour/i.test(x)) ?? body
        return { task: { title: first.replace(/^.*?(peux-tu|pourrais-tu|merci de)\s+/i, '').replace(/\s*(avant|d['’]ici|pour)\s+\w+.*$/i, '').trim().replace(/^./, (c) => c.toUpperCase()), dueAt: isoDay(d), quote: m[0] } }
      },
      async markRead(id) { mails = mails.map((x) => (x.id === id ? { ...x, unread: false } : x)) },
      async claim(mailId, _suiviId, action) {
        const m = findMail(mailId)
        if (!m) return
        const at = new Date()
        const next = { take: claimTake, release: claimRelease, handle: claimHandle, reopen: claimReopen, takeOver: claimTakeOver }[action](m, ME, at)
        replaceMail(next)
        if (m.suivi) {
          const status = next.claim?.state === 'handled' ? 'done' : next.claim?.state === 'taken' ? 'inProgress' : 'new'
          suivis = suivis.map((s) => (s.id === m.suivi ? { ...s, status } : s))
        }
      },
      async addNote(mailId, _suiviId, text) {
        const m = findMail(mailId)
        if (m) replaceMail({ ...m, notes: [...m.notes, { id: uid('n'), author: ME, text, at: new Date().toISOString() }] })
      },
      async send(p) {
        if (p.followHours === undefined) return { followDueAt: null }
        const due = day(Math.ceil(p.followHours / 24))
        suivis = [...suivis, base(uid('s'), { subject: p.subject.replace(/^(re|tr)\s*:\s*/i, ''), contact: p.to[0].split('@')[0].replace(/[._-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()), direction: 'waiting', status: 'waiting', dueAt: due, relanceAt: due, suggestedByAi: p.followSuggestedByAi ?? false, journal: [{ at: now.toISOString(), author: ME, action: 'created' }, { at: now.toISOString(), author: ME, action: 'sent' }] })]
        return { followDueAt: due }
      },
    },
    tasks: {
      async list() { return clone(tasks) },
      async create(t) { const n = { ...t, id: uid('t') }; tasks = [...tasks, n]; return clone(n) },
      async update(id, patch) { tasks = tasks.map((t) => (t.id === id ? { ...t, ...patch } : t)); return clone(tasks.find((t) => t.id === id)!) },
      async remove(id) { tasks = tasks.filter((t) => t.id !== id) },
    },
    agenda: {
      async sources() { return clone(sources) },
      async events() { return clone(events) },
      async teamBusy(_m, from) { const d = (h: number, m: number) => { const x = new Date(from); x.setDate(x.getDate() + 2); x.setHours(h, m, 0, 0); return x.toISOString() }; return [{ member: 'Claire', start: d(10, 0), end: d(11, 30) }, { member: 'Marc', start: d(14, 0), end: d(15, 0) }] },
      async create(e) { const n = { ...e, id: uid('e') }; events = [...events, n]; return clone(n) },
    },
    accounts: {
      mail: {
        async list() { return clone(mailAccounts) },
        async create(a) { const { password: _p, ...rest } = a; const n = { ...rest, id: uid('acc') }; mailAccounts.push(n); return clone(n) },
        async update(id, patch) { const a = mailAccounts.find((x) => x.id === id)!; Object.assign(a, patch); return clone(a) },
        async remove(id) { const i = mailAccounts.findIndex((a) => a.id === id); if (i >= 0) mailAccounts.splice(i, 1) },
        async test() { return { ok: true } },
      },
      identities: {
        async list() { return clone(identities) },
        async preview(html) { const safe = sanitizeSignature(html); return { html: safe, text: signatureText(safe) } },
        async create(i) { const n: Identity = { id: uid('id'), accountId: i.accountId, name: i.name, address: i.address.toLowerCase(), replyTo: i.replyTo || null, ...sig(i), isDefault: false, primary: false }; identities = [...identities, n]; return clone(n) },
        async update(id, i) {
          const x = identities.find((y) => y.id === id)
          if (!x) throw new ApiError(404, 'Identité inconnue.')
          Object.assign(x, { name: i.name, replyTo: i.replyTo || null, ...sig(i) }, x.primary ? {} : { address: i.address.toLowerCase() })
          return clone(x)
        },
        async setDefault(id) { identities = identities.map((y) => ({ ...y, isDefault: y.id === id })); return clone(identities.find((y) => y.id === id)!) },
        async remove(id) {
          const x = identities.find((y) => y.id === id)
          if (x?.primary) throw new ApiError(409, 'L’identité principale d’un compte ne se supprime pas : elle suit l’adresse du compte.')
          identities = identities.filter((y) => y.id !== id)
          if (!identities.some((y) => y.isDefault)) identities = identities.map((y, n) => ({ ...y, isDefault: n === 0 }))
        },
      },
      calendar: {
        async list() { return clone(calendarAccounts) },
        async create(a) { const { password: _p, ...rest } = a; const n = { ...rest, kind: 'caldav' as const, id: uid('cal') }; calendarAccounts.push(n); return clone(n) },
        async remove(id) { const i = calendarAccounts.findIndex((a) => a.id === id); if (i >= 0) calendarAccounts.splice(i, 1) },
      },
      shared: {
        async list() { return [sharedInfo()] },
        async updateSender(_id, p) { sharedSender = { senderName: p.senderName?.trim() || null, signature: p.signature?.trim() ? p.signature : null }; return sharedInfo() },
      },
    },
    instance: {
      async info() { return { name: 'Oscar', registration: true, supportEmail: '', legalUrl: '', privacyUrl: '', aiExternalAllowed: true, defaultAttachmentMb: 10, setupNeeded: false } },
      async setup() { return 'jeton-demo' },
      async config() { return clone(instanceConfig) },
      async updateConfig(patch) { instanceConfig = { ...instanceConfig, ...patch }; return clone(instanceConfig) },
    },
    directory: {
      async status() { return { configured: false } },
      async search() { return [] },
    },
    settings: {
      async get() { return clone(serverSettings) },
      sortRules: {
        async list() { return clone(sortRules) },
        async create(field, value, action) {
          const v = value.trim().toLowerCase().replace(field === 'domain' ? /^@/ : /$^/, '')
          if (sortRules.some((r) => r.field === field && r.value === v)) throw new ApiError(409, 'Cette règle existe déjà.', { field: 'value' })
          if (field === 'subject' ? v.length < 3 : field === 'sender' ? !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) : !/^[a-z0-9.-]+\.[a-z]{2,}$/.test(v)) throw new ApiError(422, field === 'subject' ? 'Indique un mot ou une expression de 3 caractères au moins.' : field === 'sender' ? 'Indique une adresse complète.' : 'Indique un domaine, par exemple univ-reims.fr.', { field: 'value' })
          const r = { id: uid('sort'), field, value: v, action, enabled: true }
          sortRules.push(r)
          return clone(r)
        },
        async setEnabled(id, enabled) { const r = sortRules.find((x) => x.id === id)!; r.enabled = enabled; return clone(r) },
        async remove(id) { const i = sortRules.findIndex((x) => x.id === id); if (i >= 0) sortRules.splice(i, 1) },
      },
      templates: {
        async list() { return clone(templates) },
        async create(name, body) {
          if (!name.trim() || !body.trim()) throw new ApiError(422, !name.trim() ? 'Donne un nom au modèle (80 caractères au plus).' : 'Le texte du modèle est vide ou dépasse 5 000 caractères.', { field: !name.trim() ? 'name' : 'body' })
          const t = { id: uid('tpl'), name: name.trim(), body }
          templates.push(t)
          return clone(t)
        },
        async update(id, name, body) { const t = templates.find((x) => x.id === id)!; Object.assign(t, { name: name.trim(), body }); return clone(t) },
        async remove(id) { const i = templates.findIndex((x) => x.id === id); if (i >= 0) templates.splice(i, 1) },
      },
      followRules: {
        async list() { return clone(rules) },
        async create(kind, value) {
          const v = value.trim().toLowerCase().replace(/^@/, '')
          if (rules.some((r) => r.kind === kind && r.value === v)) throw new ApiError(409, 'Cette règle existe déjà.', { field: 'value' })
          if (kind === 'sender' ? !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) : !/^[a-z0-9.-]+\.[a-z]{2,}$/.test(v)) throw new ApiError(422, kind === 'sender' ? 'Indique une adresse complète, par exemple sophie@univ-reims.fr.' : 'Indique un domaine, par exemple univ-reims.fr.', { field: 'value' })
          const r = { id: uid('rule'), kind, value: v, enabled: true }
          rules.push(r)
          return clone(r)
        },
        async setEnabled(id, enabled) { const r = rules.find((x) => x.id === id)!; r.enabled = enabled; return clone(r) },
        async remove(id) { const i = rules.findIndex((x) => x.id === id); if (i >= 0) rules.splice(i, 1) },
      },
      async aiConfig() { return clone(aiCfg) },
      async saveAiConfig(c) { aiCfg = { kind: c.kind, baseUrl: c.baseUrl.replace(/\/$/, ''), model: c.model, hasKey: c.apiKey === undefined ? aiCfg.hasKey : c.apiKey !== '', configured: !!c.baseUrl && !!c.model }; return clone(aiCfg) },
      async testAiConfig() { return aiCfg.configured ? { ok: true, message: 'Le service répond. Oscar pourra l’utiliser.' } : { ok: false, message: 'Renseigne d’abord l’adresse et le modèle, puis enregistre.' } },
      async update(patch) { serverSettings = { ...serverSettings, ...patch }; return clone(serverSettings) },
    },
    system: {
      async notifications() { return clone(notifications) },
      async markAllRead() { notifications = notifications.map((n) => ({ ...n, read: true })) },
      async connections() { return clone(connections) },
      async syncNow() {
        connections = connections.map((c) => (c.id === 'google' ? c : { ...c, state: 'ok' as const, lastOk: new Date().toISOString() }))
        return clone(connections)
      },
      async log() { return clone(log) },
    },
    auth: {
      async config() { return { password: true, registration: true, cas: true, dev: true } },
      async login() { return 'mock-token' },
      async register() { return 'mock-token' },
      async forgot() { return {} },
      async reset() { /* rien */ },
      async probe(p) {
        const ok = !/fail|erreur/i.test(p.imapHost)
        return { imap: { ok, message: ok ? 'Réception : connexion réussie.' : 'Réception : connexion impossible. Vérifie l’adresse du serveur, le port, le chiffrement et le mot de passe.' }, smtp: { ok: true, message: 'Envoi : connexion réussie.' } }
      },
      async devLogin() { return 'mock-token' },
      async exchange() { return 'mock-token' },
      async me() { return { username: ME.toLowerCase(), displayName: ME, email: 'david@iut-troyes.fr', hasPassword: true } },
      async changePassword() { /* rien */ },
      async deleteAccount() { /* rien */ },
      async logout() { /* rien */ },
    },
  }
}
