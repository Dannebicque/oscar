import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api'
import type { AppNotification, Connection, LogEntry } from '../domain/types'

export const useSystemStore = defineStore('system', () => {
  const notifications = ref<AppNotification[]>([])
  const connections = ref<Connection[]>([])
  const log = ref<LogEntry[]>([])

  const unreadCount = computed(() => notifications.value.filter((n) => !n.read).length)
  const hasError = computed(() => connections.value.some((c) => c.state === 'error'))
  const lastSync = computed(() => connections.value.map((c) => c.lastOk).filter((d): d is string => !!d).sort().at(-1) ?? null)

  async function load() {
    ;[notifications.value, connections.value, log.value] = await Promise.all([api.system.notifications(), api.system.connections(), api.system.log()])
  }
  async function markAllRead() {
    await api.system.markAllRead()
    notifications.value = notifications.value.map((n) => ({ ...n, read: true }))
  }
  async function retry(id: Connection['id']) {
    const c = await api.system.retryConnection(id)
    connections.value = connections.value.map((x) => (x.id === id ? c : x))
    return c
  }
  return { notifications, connections, log, unreadCount, hasError, lastSync, load, markAllRead, retry }
})
