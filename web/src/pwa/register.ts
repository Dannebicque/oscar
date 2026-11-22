/**
 * PWA (U-03, N-09) : enregistre le service worker en production seulement (jamais en développement, ni dans l'application de bureau
 * qui charge ses fichiers localement). À la déconnexion, les données gardées pour le hors-ligne sont effacées.
 */
import { isDesktop } from '../desktop/bridge'

export function registerServiceWorker() {
  // La démo publique (VITE_DEMO) n'installe pas de service worker : elle vit sous un sous-chemin du site de présentation.
  if (!import.meta.env.PROD || import.meta.env.VITE_DEMO === 'true' || isDesktop() || typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return
  window.addEventListener('load', () => { void navigator.serviceWorker.register('/sw.js').catch(() => undefined) })
}

/** Vide les réponses d'API gardées pour le hors-ligne (déconnexion, session expirée) : elles ne doivent pas survivre à la session. */
export function clearOfflineData() {
  try {
    if (typeof caches !== 'undefined') void caches.delete('oscar-data-v1')
    navigator.serviceWorker?.controller?.postMessage('oscar:clear')
  } catch { /* pas de cache disponible : rien à effacer */ }
}
