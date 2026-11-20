import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, apiMode, session } from '../api'
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
      } catch { saveToken(null) }
    }
    checked.value = true
  }

  async function adopt(token: string) {
    saveToken(token)
    const me = await api.auth.me()
    session.name = me.displayName
    session.username = me.username
  }
  async function loginDev(username: string) { await adopt(await api.auth.devLogin(username)) }
  async function finishCas(code: string) { await adopt(await api.auth.exchange(code)) }
  async function logout() {
    await api.auth.logout()
    saveToken(null)
  }
  return { checked, authenticated, restore, loginDev, finishCas, logout }
})
