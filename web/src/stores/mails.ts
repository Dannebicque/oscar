import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { api, ME } from '../api'
import * as claims from '../domain/claim'
import { clock } from '../domain/clock'
import type { Mail, MailFolder } from '../domain/types'
import { useSuivisStore } from './suivis'

export const useMailsStore = defineStore('mails', () => {
  const items = ref<Mail[]>([])
  const loading = ref(false)
  const error = ref(false)

  const inFolder = (mailbox: string, folder: MailFolder) =>
    computed(() => items.value.filter((m) => m.mailbox === mailbox && m.folder === folder).sort((a, b) => b.at.localeCompare(a.at)))
  const unread = (mailbox: string) => items.value.filter((m) => m.mailbox === mailbox && m.folder === 'inbox' && m.unread).length
  const byId = (id: string) => items.value.find((m) => m.id === id)
  /** Retrouve un mail par son id ou par son Message-ID (c'est ce que stocke un suivi). */
  const find = (ref: string | null | undefined) => (ref ? items.value.find((m) => m.id === ref || m.messageId === ref) : undefined)
  const refOf = (m: Mail) => m.messageId ?? m.id
  const loadingBody = ref<Set<string>>(new Set())
  const bodyError = ref<Set<string>>(new Set())
  const forSuivi = (suiviId: string) => items.value.find((m) => m.suivi === suiviId)

  async function load() {
    loading.value = true
    error.value = false
    try { items.value = await api.mails.list(); link() } catch { error.value = true } finally { loading.value = false }
  }
  /** Rattache chaque mail à son suivi par Message-ID, quelle que soit la boîte (F-44). */
  function link() {
    const bySuivi = new Map(useSuivisStore().items.filter((s) => s.mailId).map((s) => [s.mailId!, s.id]))
    const norm = (id: string) => id.trim().replace(/^<|>$/g, '').toLowerCase()
    const index = new Map([...bySuivi].map(([k, v]) => [norm(k), v]))
    // Le mail lui-même, sinon le fil auquel il répond.
    const suiviOf = (m: Mail) => index.get(norm(refOf(m))) ?? (m.parents ?? []).map((p) => index.get(norm(p))).find(Boolean) ?? null
    items.value = items.value.map((m) => (m.suivi ? m : { ...m, suivi: suiviOf(m) }))
  }
  watch(() => useSuivisStore().items, link)

  /** Corps lu à la demande (HTTP) ; les données simulées l'ont déjà. */
  async function loadBody(id: string) {
    const m = byId(id)
    if (!m || m.paragraphs.length || loadingBody.value.has(id)) return
    loadingBody.value = new Set([...loadingBody.value, id])
    bodyError.value.delete(id)
    try {
      const paragraphs = await api.mails.body(id)
      put({ ...byId(id)!, paragraphs })
    } catch {
      bodyError.value = new Set([...bodyError.value, id])
    } finally {
      const next = new Set(loadingBody.value)
      next.delete(id)
      loadingBody.value = next
    }
  }

  const put = (m: Mail) => { items.value = items.value.map((x) => (x.id === m.id ? m : x)) }

  async function markRead(id: string) {
    const m = byId(id)
    if (!m?.unread) return
    await api.mails.markRead(id)
    put({ ...m, unread: false })
  }

  async function applyClaim(id: string, fn: (m: Mail, me: string, at: Date) => Mail) {
    const m = byId(id)
    if (!m) return
    const next = fn(m, ME, clock.now())
    const saved = await api.mails.setClaim(id, next.claim!, next.activity.at(-1)!, next.composing)
    put(saved)
    // Le suivi lié reflète la prise en charge : « pris par X », jamais un responsable désigné d'office (F-67).
    if (m.suivi) {
      const status = next.claim?.state === 'handled' ? 'done' : next.claim?.state === 'taken' ? 'inProgress' : 'new'
      await useSuivisStore().setStatus(m.suivi, status)
    }
  }
  const take = (id: string) => applyClaim(id, claims.take)
  const release = (id: string) => applyClaim(id, claims.release)
  const handle = (id: string) => applyClaim(id, claims.handle)
  const reopen = (id: string) => applyClaim(id, claims.reopen)
  const takeOver = (id: string) => applyClaim(id, claims.takeOver)

  async function addNote(id: string, text: string) {
    put(await api.mails.addNote(id, { id: `n${Date.now()}`, author: ME, text, at: clock.now().toISOString() }))
  }

  /** « Créer une demande » depuis un mail reçu (F-50). */
  async function follow(id: string, opts: { suggestedByAi?: boolean; dueAt: string; reason?: string | null }) {
    const m = byId(id)
    if (!m) return undefined
    const s = await useSuivisStore().create({
      subject: m.subject.replace(/^(re|tr)\s*:\s*/i, ''), contact: m.from.name, direction: 'toHandle', dueAt: opts.dueAt,
      mailId: refOf(m), mailbox: m.mailbox, suggestedByAi: opts.suggestedByAi ?? false, reason: opts.reason ?? null,
    })
    await api.mails.linkSuivi(id, s.id)
    put({ ...m, suivi: s.id })
    return s
  }

  return { items, loading, error, loadingBody, bodyError, inFolder, unread, byId, find, refOf, loadBody, link, forSuivi, load, markRead, take, release, handle, reopen, takeOver, addNote, follow }
})
