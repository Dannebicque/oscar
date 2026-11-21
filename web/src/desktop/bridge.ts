/**
 * Pont avec l'application de bureau (Tauri 2). Le même code tourne dans le navigateur : sans Tauri, tout ici est inerte.
 * On passe par l'objet global `window.__TAURI__` (`withGlobalTauri`), sans dépendance npm supplémentaire pour le web.
 *
 * Ce que le bureau apporte (§3) : secrets dans le trousseau du système (N-04), notifications natives (F-75),
 * raccourci global de capture rapide (N-03), icône de barre système.
 */
interface TauriGlobal {
  core: { invoke<T>(cmd: string, args?: Record<string, unknown>): Promise<T> }
  event: { listen(name: string, cb: (e: { payload: unknown }) => void): Promise<() => void> }
  notification?: {
    isPermissionGranted(): Promise<boolean>
    requestPermission(): Promise<string>
    sendNotification(o: { title: string; body?: string }): void
  }
}

const tauri = (): TauriGlobal | null => (typeof window !== 'undefined' && (window as unknown as { __TAURI__?: TauriGlobal }).__TAURI__) || null

export const isDesktop = () => tauri() !== null

/** Jeton de session dans le trousseau du système (macOS Keychain, Windows Credential Manager, Secret Service) et non dans localStorage. */
export async function readStoredToken(): Promise<string | null> {
  try { return (await tauri()?.core.invoke<string | null>('token_get')) ?? null } catch { return null }
}
export async function storeToken(token: string | null): Promise<void> {
  const t = tauri()
  if (!t) return
  try { await t.core.invoke(token ? 'token_set' : 'token_delete', token ? { token } : undefined) } catch { /* trousseau indisponible : la session ne survivra pas au redémarrage */ }
}

/** Notification native ; demande l'autorisation la première fois. Renvoie false si elle est refusée ou indisponible. */
export async function nativeNotify(title: string, body: string): Promise<boolean> {
  const n = tauri()?.notification
  if (!n) return false
  try {
    const ok = (await n.isPermissionGranted()) || (await n.requestPermission()) === 'granted'
    if (ok) n.sendNotification({ title, body })
    return ok
  } catch { return false }
}

/** Écoute un événement envoyé par le côté natif (raccourci global, menu de la barre système). Renvoie la fonction qui arrête l'écoute. */
export async function onNative(name: string, cb: () => void): Promise<() => void> {
  const t = tauri()
  if (!t) return () => {}
  try { return await t.event.listen(name, () => cb()) } catch { return () => {} }
}
