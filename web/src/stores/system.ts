import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api'
import type { AppNotification, Connection, LogEntry } from '../domain/types'

export const useSystemStore = defineStore('system', () => {
  /** Tout ce que le serveur a remis ; le panneau n'affiche que ce que l'utilisateur veut y voir (canal « application »). */
  const all = ref<AppNotification[]>([])
  const notifications = computed(() => all.value.filter((n) => n.inApp !== false))
  /** Pour le bureau (Tauri) : ce qui doit devenir une notification native. */
  const desktopFeed = computed(() => all.value.filter((n) => n.desktop !== false))
  const connections = ref<Connection[]>([])
  const log = ref<LogEntry[]>([])
  const syncing = ref(false)

  const unreadCount = computed(() => notifications.value.filter((n) => !n.read).length)
  const hasError = computed(() => connections.value.some((c) => c.state === 'error'))
  const lastSync = computed(() => connections.value.map((c) => c.lastOk).filter((d): d is string => !!d).sort().at(-1) ?? null)

  /** Rafraîchit alertes, connexions et journal. Une panne réseau ne vide jamais l'écran : on garde le dernier état connu. */
  async function load() {
    try {
      ;[all.value, connections.value, log.value] = await Promise.all([api.system.notifications(), api.system.connections(), api.system.log()])
    } catch { /* dernier état conservé */ }
  }
  async function markAllRead() {
    await api.system.markAllRead()
    all.value = all.value.map((n) => ({ ...n, read: true }))
  }
  /** « Réessayer » : resynchronise maintenant et renvoie l'état réel. */
  async function syncNow() {
    syncing.value = true
    try {
      connections.value = await api.system.syncNow()
      log.value = await api.system.log()
    } finally {
      syncing.value = false
    }
  }
  return { notifications, desktopFeed, connections, log, syncing, unreadCount, hasError, lastSync, load, markAllRead, syncNow }
})
