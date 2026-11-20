import { addDays, atTime, isoDay } from '../domain/dates'
import type { Api } from './types'
import type {
  AppNotification, CalEvent, CalendarSource, Connection, LogEntry, Mail, Suivi, Task,
} from '../domain/types'

export const ME = 'David'
export const SHARED = { id: 'contact', address: 'contact@iut-troyes.fr', members: ['Claire', 'Marc', 'Léa', ME] }

const clone = <T>(v: T): T => structuredClone(v)
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
    base('s6', { subject: 'Question d’inscription', contact: 'Camille Roux', direction: 'toHandle', status: 'inProgress', mailbox: 'contact', responsible: null, dueAt: day(1), mailId: 'c1', journal: [entry(5, 'Claire', 'take')] }),
    base('s7', { subject: 'Demande de visite de l’IUT', contact: 'Lycée Camus', direction: 'toHandle', status: 'new', mailbox: 'contact', responsible: null, dueAt: day(4), mailId: 'c2', suggestedByAi: true, reason: 'Arrivé sur contact@ depuis 28 h, personne ne l’a pris.' }),
  ]

  const m = (id: string, p: Partial<Mail>): Mail => ({
    id, mailbox: 'personal', folder: 'inbox', from: { name: '', address: '' }, to: [{ name: ME, address: 'david@iut-troyes.fr' }],
    subject: '', paragraphs: [], at: hoursAgo(3), unread: false, attachments: [], suivi: null, claim: null, composing: null,
    notes: [], activity: [], hints: {}, ...p,
  })
  const claim = (state: 'new' | 'taken' | 'handled', by: string | null = null) => ({ state, by })

  let mails: Mail[] = [
    m('m1', { from: { name: 'Sophie Laurent', address: 'sophie.laurent@univ-reims.fr' }, subject: 'Re : Planning des soutenances', at: hoursAgo(2), unread: true, suivi: 's1', paragraphs: ['Bonjour David,', 'Merci pour le planning. Peux-tu confirmer le nombre de participants avant mardi ? Nous préparons le jury jeudi à 10h.', 'Bien cordialement,', 'Sophie'], hints: { event: { title: 'Préparation du jury', start: iso(6, 10), end: iso(6, 11), quote: 'jeudi à 10h' }, task: { title: 'Confirmer le nombre de participants', dueAt: day(4), quote: 'avant mardi' } } }),
    m('m5', { from: { name: 'Claire Roux', address: 'claire.roux@iut-troyes.fr' }, subject: 'Disponibilités pour la réunion pédagogique', at: hoursAgo(5), unread: true, paragraphs: ['Bonjour,', 'Pourrais-tu m’indiquer tes disponibilités la semaine du 30 novembre pour une réunion d’une heure ?', 'Merci !', 'Claire'], hints: { availability: { slots: [0, 1, 2, 3, 4].map((i) => ({ start: iso(10 + i, 9 + i), end: iso(10 + i, 10 + i) })) } } }),
    m('m2', { folder: 'sent', from: { name: ME, address: 'david@iut-troyes.fr' }, to: [{ name: 'Marc Dubois', address: 'marc.dubois@imprimerie.fr' }], subject: 'Devis des affiches', at: hoursAgo(52), suivi: 's2', paragraphs: ['Bonjour Marc,', 'Pourriez-vous m’envoyer le devis pour 40 affiches A2 ?', 'Merci, David'] }),
    m('m3', { from: { name: 'Scolarité', address: 'scolarite@iut-troyes.fr' }, subject: 'Salle B104 pour le jury', at: hoursAgo(96), suivi: 's3', paragraphs: ['Bonjour, la salle B104 est disponible sous réserve de confirmation avant vendredi.'] }),
    m('m4', { folder: 'sent', from: { name: ME, address: 'david@iut-troyes.fr' }, to: [{ name: 'Claire Petit', address: 'claire.petit@entreprise.fr' }], subject: 'Convention de stage', at: hoursAgo(20), suivi: 's4', paragraphs: ['Bonjour Claire, ci-joint la convention à signer.'], attachments: [{ name: 'convention.pdf', size: '184 Ko' }] }),
    m('m6', { from: { name: 'Service achats', address: 'achats@iut-troyes.fr' }, subject: 'Matériel de TP livré', at: hoursAgo(160), paragraphs: ['Le matériel est arrivé au magasin.'] }),
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
    { id: 'e1', calendar: 'iut', title: 'Point équipe', start: iso(0, 9), end: iso(0, 9, 45), location: 'Salle B12' },
    { id: 'e2', calendar: 'iut', title: 'Soutenance de stage : M. Lemoine', start: iso(0, 14), end: iso(0, 15), location: 'Salle B104' },
    { id: 'e3', calendar: 'perso', title: 'Dentiste', start: iso(0, 17), end: iso(0, 18), location: null },
    { id: 'e4', calendar: 'iut', title: 'Réunion pédagogique', start: iso(1, 10), end: iso(1, 11, 30), location: 'Visio' },
    { id: 'e5', calendar: 'iut', title: 'Conseil de département', start: iso(2, 14), end: iso(2, 16), location: 'Amphi 2' },
    { id: 'e6', calendar: 'iut', title: 'Jury de stage', start: iso(4, 9), end: iso(4, 12), location: 'Salle B104' },
  ]

  let notifications: AppNotification[] = [
    { id: 'n1', kind: 'replyReceived', title: 'Sophie Laurent a répondu à « Planning des soutenances ».', at: hoursAgo(2), read: false, to: 'suivis' },
    { id: 'n2', kind: 'relanceDue', title: 'Relance due : « Devis des affiches ».', at: hoursAgo(4), read: false, to: 'suivis' },
    { id: 'n3', kind: 'connectionFailed', title: 'Google Agenda : reconnexion nécessaire.', at: hoursAgo(9), read: false, to: 'journal' },
    { id: 'n4', kind: 'invitation', title: 'Invitation : Conseil de département.', at: hoursAgo(30), read: true, to: 'agenda' },
  ]
  let connections: Connection[] = [
    { id: 'mail', state: 'ok', detail: 'david@iut-troyes.fr', lastOk: hoursAgo(0.1) },
    { id: 'calendar', state: 'ok', detail: 'IUT · CalDAV', lastOk: hoursAgo(0.1) },
    { id: 'google', state: 'error', detail: 'Perso · Google Agenda', lastOk: hoursAgo(9) },
    { id: 'ai', state: 'ok', detail: 'ai.university', lastOk: hoursAgo(0.1) },
  ]
  const log: LogEntry[] = [
    { id: 'l1', at: hoursAgo(9), account: 'Google Agenda', type: 'auth', message: 'Le jeton d’accès a expiré.', action: 'reconnect' },
    { id: 'l2', at: hoursAgo(26), account: 'Messagerie', type: 'sync', message: 'Le serveur IMAP n’a pas répondu à temps. Deux nouvelles tentatives ont réussi.', action: 'none' },
  ]

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
    },
    mails: {
      async list() { return clone(mails) },
      async body(id) { return clone(mails.find((x) => x.id === id)?.paragraphs ?? []) },
      async markRead(id) { mails = mails.map((x) => (x.id === id ? { ...x, unread: false } : x)) },
      async setClaim(id, c, e, composing) {
        mails = mails.map((x) => (x.id === id ? { ...x, claim: c, composing: composing === undefined ? x.composing : composing, activity: [...x.activity, e] } : x))
        return clone(mails.find((x) => x.id === id)!)
      },
      async addNote(id, note) {
        mails = mails.map((x) => (x.id === id ? { ...x, notes: [...x.notes, note] } : x))
        return clone(mails.find((x) => x.id === id)!)
      },
      async linkSuivi(id, suiviId) { mails = mails.map((x) => (x.id === id ? { ...x, suivi: suiviId } : x)) },
      async send() { /* rien ne part sans clic : ici, simulé */ },
    },
    tasks: {
      async list() { return clone(tasks) },
      async create(t) { const n = { ...t, id: uid('t') }; tasks = [...tasks, n]; return clone(n) },
      async update(id, patch) { tasks = tasks.map((t) => (t.id === id ? { ...t, ...patch } : t)); return clone(tasks.find((t) => t.id === id)!) },
    },
    agenda: {
      async sources() { return clone(sources) },
      async events() { return clone(events) },
      async create(e) { const n = { ...e, id: uid('e') }; events = [...events, n]; return clone(n) },
    },
    system: {
      async notifications() { return clone(notifications) },
      async markAllRead() { notifications = notifications.map((n) => ({ ...n, read: true })) },
      async connections() { return clone(connections) },
      async retryConnection(id) {
        connections = connections.map((c) => (c.id === id ? { ...c, state: 'ok' as const, lastOk: new Date().toISOString() } : c))
        return clone(connections.find((c) => c.id === id)!)
      },
      async log() { return clone(log) },
    },
  }
}
