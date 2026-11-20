import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Toast {
  id: number
  message: string
  tone: 'info' | 'success' | 'error'
  undo?: () => unknown
}

let seq = 0

export const useUiStore = defineStore('ui', () => {
  const toasts = ref<Toast[]>([])
  /** Texte de la zone live unique (N-02) : annonce sans voler le focus. */
  const announcement = ref('')

  function announce(message: string) {
    // Vider puis remplir force la relecture, même si le message est identique.
    announcement.value = ''
    queueMicrotask(() => { announcement.value = message })
  }

  function dismiss(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  /** Les erreurs restent jusqu'à fermeture ; le reste disparaît seul (annulation possible jusque-là). */
  function notify(message: string, opts: { tone?: Toast['tone']; undo?: Toast['undo']; ms?: number } = {}) {
    const t: Toast = { id: ++seq, message, tone: opts.tone ?? 'success', undo: opts.undo }
    toasts.value = [...toasts.value, t]
    announce(message)
    if (t.tone !== 'error') setTimeout(() => dismiss(t.id), opts.ms ?? 8000)
    return t.id
  }

  return { toasts, announcement, announce, dismiss, notify }
})
