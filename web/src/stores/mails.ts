import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { api, ApiError } from '../api'
import type { SendPayload } from '../api/types'
import type { ClaimAction, Mail, MailFolder, Suivi } from '../domain/types'
import { useSuivisStore } from './suivis'

const norm = (id: string) => id.trim().replace(/^<|>$/g, '').toLowerCase()

export const useMailsStore = defineStore('mails', () => {
  const items = ref<Mail[]>([])
  const loading = ref(false)
  const error = ref(false)
  const loadingBody = ref<Set<string>>(new Set())
  const bodyError = ref<Set<string>>(new Set())
  const hintsLoaded = ref<Set<string>>(new Set())

  const inFolder = (mailbox: string, folder: MailFolder) =>
    computed(() => items.value.filter((m) => m.mailbox === mailbox && m.folder === folder).sort((a, b) => b.at.localeCompare(a.at)))
  const unread = (mailbox: string) => items.value.filter((m) => m.mailbox === mailbox && m.folder === 'inbox' && m.unread).length
  const byId = (id: string) => items.value.find((m) => m.id === id)
  /** Retrouve un mail par son id ou par son Message-ID (c'est ce que stocke un suivi). */
  const find = (ref: string | null | undefined) => (ref ? items.value.find((m) => m.id === ref || m.messageId === ref) : undefined)
  const refOf = (m: Mail) => m.messageId ?? m.id
  const forSuivi = (suiviId: string) => items.value.find((m) => m.suivi === suiviId)

  async function load() {
    loading.value = true
    error.value = false
    try { items.value = await api.mails.list(); link() } catch { error.value = true } finally { loading.value = false }
  }
  const put = (m: Mail) => { items.value = items.value.map((x) => (x.id === m.id ? m : x)) }

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
      put({ ...byId(id)!, paragraphs: await api.mails.body(id) })
    } catch {
      bodyError.value = new Set([...bodyError.value, id])
    } finally {
      const next = new Set(loadingBody.value)
      next.delete(id)
      loadingBody.value = next
    }
  }

  /** Suggestions de l'IA pour ce mail, une fois par mail. Une panne n'empêche jamais de lire le message. */
  async function loadHints(id: string) {
    const m = byId(id)
    if (!m || hintsLoaded.value.has(id)) return
    hintsLoaded.value = new Set([...hintsLoaded.value, id])
    try { put({ ...byId(id)!, hints: await api.mails.hints(id) }) } catch { /* pas de suggestions, pas de blocage */ }
  }

  async function markRead(id: string) {
    const m = byId(id)
    if (!m?.unread) return
    await api.mails.markRead(id)
    put({ ...m, unread: false })
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
    put({ ...m, suivi: s.id })
    return s
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

  return { items, loading, error, loadingBody, bodyError, inFolder, unread, byId, find, refOf, forSuivi, load, loadBody, loadHints, link, markRead, take, release, handle, reopen, takeOver, addNote, follow, send, conflictMessage }
})
