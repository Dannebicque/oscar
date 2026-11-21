import { watch } from 'vue'
import { i18n } from '../i18n'
import { useSystemStore } from '../stores/system'
import { isDesktop, nativeNotify } from './bridge'

const KEY = 'oscar.desktop.notified'

const loadSeen = (): string[] => { try { return JSON.parse(localStorage.getItem(KEY) ?? '[]') as string[] } catch { return [] } }
const saveSeen = (ids: string[]) => { try { localStorage.setItem(KEY, JSON.stringify(ids.slice(-300))) } catch { /* stockage indisponible : on pourrait re-notifier au redémarrage */ } }

/**
 * Notifications natives du bureau (F-75) : ce que le serveur a remis sur le canal « bureau » et qui n'est pas lu.
 * Une seule fois par notification, au plus trois à la fois (le reste est regroupé), jamais dans le navigateur.
 */
export function useDesktopNotifications() {
  if (!isDesktop()) return
  const system = useSystemStore()
  watch(() => system.desktopFeed, async (feed) => {
    const seen = loadSeen()
    const fresh = feed.filter((n) => !n.read && !seen.includes(n.id))
    if (fresh.length === 0) return
    const t = i18n.global.t
    const title = t('app.name')
    for (const n of fresh.slice(0, 3)) await nativeNotify(title, t(`alerts.messages.${n.kind}`, n.params, Number(n.params.n ?? 1)))
    if (fresh.length > 3) await nativeNotify(title, t('alerts.moreDesktop', { n: fresh.length - 3 }))
    saveSeen([...seen, ...fresh.map((n) => n.id)])
  }, { immediate: true })
}
