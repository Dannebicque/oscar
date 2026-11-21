import { onBeforeUnmount, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { MAIN_NAV } from '../components/layout/NavLinks'

/**
 * Balayage horizontal entre les vues principales (U-02), sur écran tactile seulement. Toujours DOUBLÉ par les boutons de la barre
 * de navigation : le geste n'est jamais le seul chemin (U-05, WCAG 2.5.1). Prudent : ignoré dans un champ, une fenêtre, un
 * contenu qui défile à l'horizontale ou l'agenda (le geste y sert déjà), et sur toute route qui n'est pas une vue principale.
 */
const MIN_DX = 90

export function swipeTarget(dx: number, dy: number, current: string | undefined): string | null {
  if (Math.abs(dx) < MIN_DX || Math.abs(dx) < Math.abs(dy) * 2) return null
  const names = MAIN_NAV.map((n) => String(n.name))
  const i = names.indexOf(String(current))
  if (i < 0) return null
  const next = dx < 0 ? i + 1 : i - 1 // glisser vers la gauche : vue suivante
  return next >= 0 && next < names.length ? names[next] : null
}

function blocked(el: Element | null): boolean {
  for (let e = el; e; e = e.parentElement) {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.tagName) || (e as HTMLElement).isContentEditable) return true
    if (e.getAttribute('role') === 'dialog' || e.classList.contains('fc')) return true
    const o = getComputedStyle(e).overflowX
    if ((o === 'auto' || o === 'scroll') && e.scrollWidth > e.clientWidth + 1) return true
  }
  return false
}

export function useSwipeNav() {
  const route = useRoute()
  const router = useRouter()
  let start: { x: number; y: number; ok: boolean } | null = null

  const down = (e: PointerEvent) => { start = e.pointerType === 'touch' ? { x: e.clientX, y: e.clientY, ok: !blocked(e.target as Element) } : null }
  const up = (e: PointerEvent) => {
    const s = start
    start = null
    if (!s || !s.ok || e.pointerType !== 'touch') return
    const to = swipeTarget(e.clientX - s.x, e.clientY - s.y, route.name as string | undefined)
    if (to) void router.push({ name: to })
  }
  const cancel = () => { start = null }
  onMounted(() => { document.addEventListener('pointerdown', down); document.addEventListener('pointerup', up); document.addEventListener('pointercancel', cancel) })
  onBeforeUnmount(() => { document.removeEventListener('pointerdown', down); document.removeEventListener('pointerup', up); document.removeEventListener('pointercancel', cancel) })
}
