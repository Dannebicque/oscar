import { reactive } from 'vue'

const KEY = 'oscar.token'

/** Session de l'utilisateur connecté. Le jeton est opaque, révocable et expire (N-05). */
export const session = reactive({ name: 'David', username: 'david', token: null as string | null })

export function loadToken(): string | null {
  try { return localStorage.getItem(KEY) } catch { return null }
}
export function saveToken(token: string | null) {
  session.token = token
  try {
    if (token) localStorage.setItem(KEY, token)
    else localStorage.removeItem(KEY)
  } catch { /* stockage indisponible : la session ne survivra pas au rechargement */ }
}
session.token = loadToken()

/** Appelé quand l'API répond 401 : l'application renvoie à la connexion. */
export const authEvents = { onUnauthorized: () => {} }
