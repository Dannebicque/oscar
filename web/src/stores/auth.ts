import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, apiMode, session } from '../api'
import { ApiError } from '../api/types'
import { authEvents, saveToken } from '../api/session'

/** Connexion : CAS en production, connexion de développement en local. En démo hors ligne, aucune connexion n'est requise. */
export const useAuthStore = defineStore('auth', () => {
  const checked = ref(apiMode === 'mock')
  const authenticated = computed(() => apiMode === 'mock' || !!session.token)

  authEvents.onUnauthorized = () => { saveToken(null) }

  async function restore() {
    if (apiMode === 'mock') { checked.value = true; return }
    if (session.token) {
      try {
        const me = await api.auth.me()
        session.name = me.displayName
        session.username = me.username
        session.email = me.email ?? null
        session.hasPassword = !!me.hasPassword
        session.admin = !!me.admin
      } catch (e) {
        // Seul un 401 ferme la session : une panne passagère (502, réseau) ne doit pas déconnecter.
        if (e instanceof ApiError && e.status === 401) saveToken(null)
      }
    }
    checked.value = true
  }

  async function adopt(token: string) {
    saveToken(token)
    const me = await api.auth.me()
    session.name = me.displayName
    session.username = me.username
    session.email = me.email ?? null
    session.hasPassword = !!me.hasPassword
    session.admin = !!me.admin
  }
  async function login(email: string, password: string) { await adopt(await api.auth.login(email, password)) }
  async function register(name: string, email: string, password: string) { await adopt(await api.auth.register(name, email, password)) }
  async function loginDev(username: string) { await adopt(await api.auth.devLogin(username)) }
  async function finishCas(code: string) { await adopt(await api.auth.exchange(code)) }
  /** Exporte les données de l'utilisateur (N-06) : mêmes points d'accès que l'application, aucun secret (mots de passe de messagerie jamais renvoyés). */
  async function exportData() {
    const [suivis, tasks, mails, accounts, calendars, settings, notifications, slotHolds] = await Promise.all([
      api.suivis.list(), api.tasks.list(), api.mails.list(), api.accounts.mail.list(), api.accounts.calendar.list(), api.settings.get(), api.system.notifications(), api.agenda.holds(),
    ])
    const blob = new Blob([JSON.stringify({ exportedAt: new Date().toISOString(), profile: { name: session.name, username: session.username, email: session.email }, settings, accounts: { mail: accounts, calendar: calendars }, suivis, tasks, mailHeaders: mails, notifications, slotHolds }, null, 2)], { type: 'application/json' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = 'oscar-mes-donnees.json'
    a.click()
    URL.revokeObjectURL(a.href)
  }
  async function deleteAccount(secret: { password?: string; confirm?: string }) {
    await api.auth.deleteAccount(secret)
    saveToken(null)
  }
  async function logout() {
    await api.auth.logout()
    saveToken(null)
  }
  return { adopt, checked, authenticated, restore, login, register, exportData, deleteAccount, loginDev, finishCas, logout }
})
