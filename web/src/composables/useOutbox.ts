import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, api } from '../api'
import { enqueue, pending, remove } from '../pwa/outbox'
import type { SendPayload } from '../api/types'
import { useUiStore } from '../stores/ui'

/** État de la connexion et file d'envoi (F-80). Partagé : un seul état pour toute l'application. */
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine)
const queued = ref(0)
let flushing = false

async function refresh() { queued.value = (await pending()).length }

/** Un échec réseau (pas de réponse du serveur) : le message attend. Une réponse du serveur (refus, erreur) n'est jamais mise en file. */
export const isOffline = (e: unknown) => (e instanceof ApiError && e.status === 0) || (typeof navigator !== 'undefined' && !navigator.onLine)

export function useOutbox() {
  const { t } = useI18n()
  const ui = useUiStore()

  async function queue(payload: SendPayload) {
    await enqueue(payload)
    await refresh()
    ui.notify(t('offline.queued'), { tone: 'info' })
  }

  /** Envoie la file dans l'ordre ; un message qui échoue pour une autre raison que le réseau reste en file et est signalé. */
  async function flush() {
    if (flushing || !online.value) return
    flushing = true
    try {
      for (const item of await pending()) {
        try {
          await api.mails.send(item.payload)
          await remove(item.id)
          ui.notify(t('offline.sent', { subject: item.payload.subject }))
        } catch (e) {
          if (isOffline(e)) break
          ui.notify(t('offline.failed', { subject: item.payload.subject }), { tone: 'error' })
          break
        }
      }
    } finally { flushing = false; await refresh() }
  }

  const goOnline = () => { online.value = true; void flush() }
  const goOffline = () => { online.value = false }
  onMounted(() => { online.value = navigator.onLine; window.addEventListener('online', goOnline); window.addEventListener('offline', goOffline); void refresh().then(flush) })
  onBeforeUnmount(() => { window.removeEventListener('online', goOnline); window.removeEventListener('offline', goOffline) })
  return { online, queued, queue, flush, refresh }
}
