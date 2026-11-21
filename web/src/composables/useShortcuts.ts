import { onBeforeUnmount, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSettingsStore } from '../stores/settings'
import { useUiStore } from '../stores/ui'

/**
 * Raccourcis clavier alignés sur Gmail, la référence que les utilisateurs de webmail connaissent (U-11) :
 *   c écrire · / rechercher · j / k message suivant / précédent · r répondre · ? aide · g puis i s t a h pour aller à…
 * Règles d'accessibilité (WCAG 2.1.4) : jamais actifs dans un champ de saisie, une fenêtre ou avec une touche de modification,
 * et désactivables dans Réglages.
 */
export const SHORTCUT_EVENTS = { move: 'oscar:move', reply: 'oscar:reply' } as const

const GO: Record<string, string> = { i: '/mails', s: '/suivis', t: '/taches', a: '/agenda', h: '/', r: '/recap' }

export function isTyping(el: EventTarget | null): boolean {
  const e = el as HTMLElement | null
  if (!e || !('tagName' in e)) return false
  return ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.tagName) || e.isContentEditable || e.getAttribute('role') === 'combobox' || e.getAttribute('role') === 'textbox'
}

export function useShortcuts() {
  const router = useRouter()
  const settings = useSettingsStore()
  const ui = useUiStore()
  let pendingG = false
  let timer: ReturnType<typeof setTimeout> | undefined

  function onKey(e: KeyboardEvent) {
    if (!settings.s.shortcuts || e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return
    if (isTyping(e.target) || document.querySelector('[role="dialog"]')) return
    const k = e.key
    if (pendingG) {
      pendingG = false
      clearTimeout(timer)
      if (GO[k]) { e.preventDefault(); void router.push(GO[k]) }
      return
    }
    if (k === 'g') { pendingG = true; timer = setTimeout(() => (pendingG = false), 1200); return }
    if (k === 'c') { e.preventDefault(); void router.push('/ecrire') }
    else if (k === '/') {
      e.preventDefault()
      const focus = () => document.getElementById('mail-q')?.focus()
      if (document.getElementById('mail-q')) focus()
      else void router.push('/mails').then(() => setTimeout(focus, 60))
    } else if (k === '?') { e.preventDefault(); ui.shortcutsOpen = true }
    else if (k === 'j' || k === 'k') window.dispatchEvent(new CustomEvent(SHORTCUT_EVENTS.move, { detail: k === 'j' ? 1 : -1 }))
    else if (k === 'r') window.dispatchEvent(new CustomEvent(SHORTCUT_EVENTS.reply))
  }
  onMounted(() => document.addEventListener('keydown', onKey))
  onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
}
