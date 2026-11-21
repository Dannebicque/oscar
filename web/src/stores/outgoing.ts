import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { SendPayload } from '../api/types'

/**
 * Annuler l'envoi (F-73) : après le clic sur « Envoyer », le message attend quelques secondes ici avant de partir.
 * Ce n'est pas un envoi automatique : l'utilisateur a cliqué, et il garde la possibilité de se rattraper.
 * Annuler rend le message à l'éditeur ; « Envoyer maintenant » saute l'attente.
 */
export const useOutgoingStore = defineStore('outgoing', () => {
  const payload = ref<SendPayload | null>(null)
  const remaining = ref(0)
  /** Message rendu par « Annuler » (ou par un envoi échoué) : l'éditeur le reprend à son ouverture. */
  const restore = ref<SendPayload | null>(null)
  const pending = computed(() => payload.value !== null)
  let timer: ReturnType<typeof setInterval> | undefined
  let commitFn: (() => Promise<void>) | null = null

  function clear() {
    clearInterval(timer)
    timer = undefined
    payload.value = null
    commitFn = null
    remaining.value = 0
  }

  /** Garde le message `seconds` secondes puis appelle `commit`. */
  function hold(p: SendPayload, seconds: number, commit: () => Promise<void>) {
    payload.value = p
    commitFn = commit
    remaining.value = seconds
    timer = setInterval(() => {
      remaining.value -= 1
      if (remaining.value <= 0) void sendNow()
    }, 1000)
  }
  async function sendNow() {
    const fn = commitFn
    clear()
    if (fn) await fn()
  }
  /** Annule : le message est rendu pour être rouvert dans l'éditeur. */
  function cancel(): SendPayload | null {
    const p = payload.value
    clear()
    restore.value = p
    return p
  }
  function takeRestore(): SendPayload | null {
    const p = restore.value
    restore.value = null
    return p
  }
  return { payload, remaining, restore, pending, hold, sendNow, cancel, takeRestore }
})
