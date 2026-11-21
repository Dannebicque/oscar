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
  /** Fenêtre d'aide des raccourcis clavier. */
  const shortcutsOpen = ref(false)

  function announce(message: string) {
    // Vider puis remplir force la relecture, même si le message est identique.
    announcement.value = ''
    queueMicrotask(() => { announcement.value = message })
  }

  function dismiss(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  /** Les erreurs restent jusqu'à fermeture ; le reste disparaît seul après 8 s, en pause au survol et au focus. */
  function notify(message: string, opts: { tone?: Toast['tone']; undo?: Toast['undo']; ms?: number } = {}) {
    const t: Toast = { id: ++seq, message, tone: opts.tone ?? 'success', undo: opts.undo }
    toasts.value = [...toasts.value, t]
    // La notification s'annonce elle-même (zone live de Reka UI) ; la fermeture automatique est gérée par le composant.
    return t.id
  }

  return { toasts, announcement, shortcutsOpen, announce, dismiss, notify }
})
