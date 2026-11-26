import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { api, ApiError } from '../api'
import type { ScheduledMail, SendPayload } from '../api/types'
import type { ClaimAction, FolderInfo, Mail, MailFolder, MoveTarget, Suivi } from '../domain/types'
import { useSuivisStore } from './suivis'

const norm = (id: string) => id.trim().replace(/^<|>$/g, '').toLowerCase()

export const useMailsStore = defineStore('mails', () => {
  const items = ref<Mail[]>([])
  const loading = ref(false)
  const error = ref(false)
  const loadingBody = ref<Set<string>>(new Set())
  const bodyError = ref<Set<string>>(new Set())
  /** Messages que le serveur de messagerie n'a plus (supprimés ou déplacés ailleurs). */
  const gone = ref<Set<string>>(new Set())
  const hintsLoaded = ref<Set<string>>(new Set())

  const inFolder = (mailbox: string, folder: MailFolder) =>
    computed(() => items.value.filter((m) => m.mailbox === mailbox && m.folder === folder).sort((a, b) => b.at.localeCompare(a.at)))
  const unread = (mailbox: string) => items.value.filter((m) => m.mailbox === mailbox && m.folder === 'inbox' && m.unread).length
  const byId = (id: string) => items.value.find((m) => m.id === id)
  /** Retrouve un mail par son id ou par son Message-ID (c'est ce que stocke un suivi). */
  // Un Message-ID s'écrit avec ou sans chevrons selon qui l'a noté (envoi, synchro) : on compare toujours normalisé.
  const find = (ref: string | null | undefined) => (ref ? items.value.find((m) => m.id === ref || norm(m.messageId ?? '') === norm(ref)) : undefined)
  const refOf = (m: Mail) => m.messageId ?? m.id
  /** Fil de la conversation : tous les messages reliés à celui-ci par Message-ID / In-Reply-To / References, du plus ancien au plus récent (un même Message-ID reçu dans deux boîtes ne compte qu'une fois). */
  function threadOf(mail: Mail): Mail[] {
    const idsOf = (m: Mail) => [refOf(m), ...(m.parents ?? [])].map(norm)
    const ids = new Set<string>(idsOf(mail))
    let grew = true
    while (grew) {
      grew = false
      for (const m of items.value) {
        const mine = idsOf(m)
        if (mine.some((x) => ids.has(x)) && !mine.every((x) => ids.has(x))) { mine.forEach((x) => ids.add(x)); grew = true }
      }
    }
    const seen = new Map<string, Mail>()
    for (const m of items.value) {
      const key = norm(refOf(m))
      if (!ids.has(key) || m.folder === 'trash' || m.folder === 'drafts') continue
      if (!seen.has(key) || m.id === mail.id) seen.set(key, m)
    }
    return [...seen.values()].sort((a, b) => a.at.localeCompare(b.at))
  }
  /** Message à montrer quand on ouvre un suivi : le dernier du fil (la réponse reçue s'il y en a une). */
  function openTarget(ref: string | null | undefined): Mail | undefined {
    const m = find(ref)
    return m ? (threadOf(m).at(-1) ?? m) : undefined
  }
  const forSuivi = (suiviId: string) => items.value.find((m) => m.suivi === suiviId)

  /**
   * La liste ne porte que les en-têtes. Le texte, les pièces jointes et les suggestions lus à la demande sont gardés d'un rechargement à l'autre :
   * sinon le message ouvert se vidait (« pas de texte ») dès que la liste revenait après lui.
   */
  function keepLoaded(fresh: Mail[]): Mail[] {
    const before = new Map(items.value.map((m) => [m.id, m]))
    return fresh.map((m) => {
      const old = before.get(m.id)
      if (!old) return m
      return {
        ...m,
        paragraphs: m.paragraphs.length ? m.paragraphs : old.paragraphs,
        attachments: m.attachments.length ? m.attachments : old.attachments,
        hints: Object.keys(m.hints).length ? m.hints : old.hints,
        invitation: m.invitation ?? old.invitation,
      }
    })
  }

  async function load() {
    loading.value = true
    error.value = false
    try { items.value = keepLoaded(await api.mails.list()); link() } catch { error.value = true } finally { loading.value = false }
  }
  const put = (m: Mail) => { items.value = items.value.map((x) => (x.id === m.id ? m : x)) }
  /**
   * Modifie quelques champs d'un mail en partant de son état ACTUEL au moment de l'écriture. Indispensable quand plusieurs
   * chargements (corps, suggestions, lu) tournent en parallèle : copier le mail AVANT l'attente écraserait les autres résultats.
   */
  const patch = (id: string, fields: Partial<Mail>) => { items.value = items.value.map((x) => (x.id === id ? { ...x, ...fields } : x)) }

  /** État de prise en charge d'une demande d'alias, vu par la boîte partagée : « nouveau / pris par X / traité par X ». */
  function teamState(s: Suivi): Pick<Mail, 'claim' | 'notes' | 'activity' | 'composing'> {
    return {
      claim: { state: s.status === 'done' ? 'handled' : s.status === 'inProgress' ? 'taken' : 'new', by: s.takenByName ?? null },
      notes: s.notes ?? [], activity: s.journal, composing: s.composingName ?? null,
    }
  }

  /** Rattache chaque mail à son suivi par Message-ID, puis par le fil (F-44), et recopie l'état d'équipe (API). */
  function link() {
    const suivis = useSuivisStore().items
    const index = new Map(suivis.filter((s) => s.mailId).map((s) => [norm(s.mailId!), s]))
    const suiviOf = (m: Mail): Suivi | undefined => index.get(norm(refOf(m))) ?? (m.parents ?? []).map((p) => index.get(norm(p))).find(Boolean)
    items.value = items.value.map((m) => {
      const s = m.suivi ? suivis.find((x) => x.id === m.suivi) : suiviOf(m)
      const linked = { ...m, suivi: m.suivi ?? s?.id ?? null }
      // takenByName n'existe que dans les données de l'API : les données simulées portent leur état sur le mail.
      return m.mailbox !== 'personal' && s?.takenByName !== undefined ? { ...linked, ...teamState(s) } : linked
    })
  }
  watch(() => useSuivisStore().items, link)

  /** Corps lu à la demande ; les données simulées l'ont déjà. */
  async function loadBody(id: string) {
    const m = byId(id)
    if (!m || m.paragraphs.length || loadingBody.value.has(id)) return
    loadingBody.value = new Set([...loadingBody.value, id])
    bodyError.value.delete(id)
    try {
      patch(id, { paragraphs: await api.mails.body(id) })
    } catch (e) {
      if (e instanceof ApiError && e.status === 410) gone.value = new Set([...gone.value, id])
      else bodyError.value = new Set([...bodyError.value, id])
    } finally {
      const next = new Set(loadingBody.value)
      next.delete(id)
      loadingBody.value = next
    }
  }

  /** Invitation d'agenda jointe (F-25), lue à la demande, une fois par mail. Une panne n'empêche jamais de lire le message. */
  const invitationLoaded = ref<Set<string>>(new Set())
  async function loadInvitation(id: string) {
    const m = byId(id)
    if (!m || invitationLoaded.value.has(id)) return
    invitationLoaded.value = new Set([...invitationLoaded.value, id])
    try { const r = await api.mails.invitation(id); patch(id, { invitation: r.invitation, invitationResponse: r.response }) } catch { /* pas d'invitation affichée */ }
  }
  async function respondInvitation(id: string, response: 'accepted' | 'tentative' | 'declined', calendarId?: string) {
    const r = await api.mails.replyInvitation(id, { response, calendarId })
    patch(id, { invitationResponse: response })
    return r
  }

  /** Pièces jointes d'un mail, lues à la demande (une fois). Une panne n'empêche jamais de lire le message. */
  const attachmentsLoaded = ref<Set<string>>(new Set())
  async function loadAttachments(id: string) {
    const m = byId(id)
    if (!m || attachmentsLoaded.value.has(id)) return
    attachmentsLoaded.value = new Set([...attachmentsLoaded.value, id])
    try { patch(id, { attachments: await api.mails.attachments(id) }) } catch { /* pas de liste, pas de blocage */ }
  }

  /** Enregistre un brouillon sur le serveur puis rafraîchit la liste (le brouillon y apparaît dans « Brouillons »). */
  async function saveDraft(p: Parameters<typeof api.mails.saveDraft>[0]) {
    await api.mails.saveDraft(p)
    await load()
  }

  /** Déplace un message (archives, corbeille, boîte de réception). Renvoie l'annulation : remettre en boîte de réception. */
  async function moveTo(id: string, folder: MoveTarget) {
    const m = byId(id)
    if (!m) return null
    const from = m.folder as MoveTarget
    await api.mails.move(id, folder)
    patch(id, { folder })
    return async () => { await api.mails.move(id, from); patch(id, { folder: from }) }
  }

  /** Suggestions de l'IA pour ce mail, une fois par mail. Une panne n'empêche jamais de lire le message. */
  async function loadHints(id: string) {
    const m = byId(id)
    if (!m || hintsLoaded.value.has(id)) return
    hintsLoaded.value = new Set([...hintsLoaded.value, id])
    try { patch(id, { hints: await api.mails.hints(id) }) } catch { /* pas de suggestions, pas de blocage */ }
  }

  async function markRead(id: string) {
    const m = byId(id)
    if (!m?.unread) return
    // Un serveur de messagerie qui ne répond pas ne bloque pas la lecture : le message reste « non lu », ce qui est vrai.
    try { await api.mails.markRead(id) } catch { return }
    patch(id, { unread: false })
  }

  /** Prise en charge d'une demande d'alias. Rafraîchit les suivis : l'état vient du serveur. */
  async function claim(id: string, action: ClaimAction) {
    const m = byId(id)
    if (!m) return
    try {
      await api.mails.claim(id, m.suivi, action)
    } finally {
      // Même en cas de conflit (« Claire s'en occupe ») l'écran doit montrer l'état réel.
      await Promise.all([load(), useSuivisStore().load()])
    }
  }
  const take = (id: string) => claim(id, 'take')
  const release = (id: string) => claim(id, 'release')
  const handle = (id: string) => claim(id, 'handle')
  const reopen = (id: string) => claim(id, 'reopen')
  const takeOver = (id: string) => claim(id, 'takeOver')

  async function addNote(id: string, text: string) {
    const m = byId(id)
    if (!m) return
    await api.mails.addNote(id, m.suivi, text)
    await Promise.all([load(), useSuivisStore().load()])
  }

  /** « Créer une demande » depuis un mail reçu (F-50). */
  async function follow(id: string, opts: { suggestedByAi?: boolean; dueAt: string; reason?: string | null }) {
    const m = byId(id)
    if (!m) return undefined
    const s = await useSuivisStore().create({
      subject: m.subject.replace(/^(re|tr)\s*:\s*/i, ''), contact: m.from.name, direction: 'toHandle', dueAt: opts.dueAt,
      mailId: refOf(m), mailbox: m.mailbox, suggestedByAi: opts.suggestedByAi ?? false, reason: opts.reason ?? null,
    })
    patch(id, { suivi: s.id })
    return s
  }

  /** Dossiers et sous-dossiers de la messagerie (F-16). */
  const folders = ref<FolderInfo[]>([])
  async function loadFolders() { folders.value = await api.mails.folders.list().catch(() => folders.value) }
  async function createFolder(name: string, parent?: string) {
    await api.mails.folders.create(name, parent)
    await loadFolders()
  }
  async function renameFolder(path: string, name: string) {
    const r = await api.mails.folders.rename(path, name)
    // Les messages que nous connaissons suivent le renommage (le dossier et ses sous-dossiers).
    items.value = items.value.map((m) => (m.folder === `f:${path}` || m.folder.startsWith(`f:${path}/`) ? { ...m, folder: `f:${r.path}${m.folder.slice(2 + path.length)}` as Mail['folder'] } : m))
    await loadFolders()
    return r
  }
  async function removeFolder(path: string) {
    await api.mails.folders.remove(path)
    await loadFolders()
  }
  /** Ouvrir un dossier : ses derniers messages sont récupérés, puis la liste est rechargée s'il y en a de nouveaux. */
  async function openFolder(path: string) {
    if (await api.mails.folders.open(path).catch(() => 0)) await load()
  }

  /** Mails programmés (F-73) : écrits, en attente de l'heure choisie par l'utilisateur. */
  const scheduled = ref<ScheduledMail[]>([])
  async function loadScheduled() { scheduled.value = await api.mails.scheduled().catch(() => []) }
  async function schedule(p: SendPayload, sendAt: string) {
    const row = await api.mails.schedule(p, sendAt)
    scheduled.value = [...scheduled.value, row].sort((a, b) => a.sendAt.localeCompare(b.sendAt))
    return row
  }
  async function cancelScheduled(id: string) {
    const payload = await api.mails.cancelScheduled(id)
    scheduled.value = scheduled.value.filter((x) => x.id !== id)
    return payload
  }

  /** Envoi (F-73) : le suivi est créé par le serveur, seulement si le message est parti. */
  async function send(p: SendPayload) {
    const r = await api.mails.send(p)
    await Promise.all([useSuivisStore().load(), load()])
    return r
  }

  /** Message à montrer quand une action échoue, sans jamais exposer un code technique (F-79). */
  function conflictMessage(e: unknown): string | null {
    return e instanceof ApiError && e.status === 409 ? e.message : null
  }

  return { gone, openTarget, folders, loadFolders, createFolder, renameFolder, removeFolder, openFolder, scheduled, loadScheduled, schedule, cancelScheduled, items, loading, error, loadingBody, bodyError, inFolder, unread, byId, find, refOf, threadOf, forSuivi, load, loadInvitation, respondInvitation, saveDraft, loadAttachments, moveTo, loadBody, loadHints, link, markRead, take, release, handle, reopen, takeOver, addNote, follow, send, conflictMessage }
})
