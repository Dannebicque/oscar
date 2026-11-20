import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
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
  const forSuivi = (suiviId: string) => items.value.find((m) => m.suivi === suiviId)

  async function load() {
    loading.value = true
    error.value = false
    try { items.value = await api.mails.list() } catch { error.value = true } finally { loading.value = false }
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
      mailId: m.id, mailbox: m.mailbox, suggestedByAi: opts.suggestedByAi ?? false, reason: opts.reason ?? null,
    })
    await api.mails.linkSuivi(id, s.id)
    put({ ...m, suivi: s.id })
    return s
  }

  return { items, loading, error, inFolder, unread, byId, forSuivi, load, markRead, take, release, handle, reopen, takeOver, addNote, follow }
})
