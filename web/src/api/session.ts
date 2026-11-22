import { reactive } from 'vue'

import { clearOfflineData } from '../pwa/register'
import { isDesktop, readStoredToken, storeToken } from '../desktop/bridge'

const KEY = 'oscar.token'

/** Session de l'utilisateur connecté. Le jeton est opaque, révocable et expire (N-05). */
export const session = reactive({ name: 'David', username: 'david', email: null as string | null, hasPassword: false, admin: false, token: null as string | null })

export function loadToken(): string | null {
  try { return localStorage.getItem(KEY) } catch { return null }
}
export function saveToken(token: string | null) {
  session.token = token
  if (!token) clearOfflineData() // la session s'arrête : les données gardées pour le hors-ligne aussi
  if (isDesktop()) { void storeToken(token); return } // bureau : trousseau du système, jamais localStorage
  try {
    if (token) localStorage.setItem(KEY, token)
    else localStorage.removeItem(KEY)
  } catch { /* stockage indisponible : la session ne survivra pas au rechargement */ }
}
session.token = loadToken()

/** Bureau : reprend le jeton du trousseau avant le premier affichage. */
export async function restoreDesktopSession() {
  if (isDesktop() && !session.token) session.token = await readStoredToken()
}

/** Appelé quand l'API répond 401 : l'application renvoie à la connexion. */
export const authEvents = { onUnauthorized: () => {} }
