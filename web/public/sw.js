/* Oscar : service worker (PWA, N-09, F-80). Sans bibliothèque.
 *
 * - Coque de l'application : les fichiers compilés (/assets/*) sont servis du cache (ils portent leur empreinte) ; la page d'accueil
 *   est servie par le réseau d'abord, avec repli sur la dernière version connue.
 * - Données : les lectures de l'API (GET) passent par le réseau d'abord ; hors ligne, la dernière réponse connue est rendue, pour consulter
 *   la to-do et les mails récents sans réseau. JAMAIS d'écriture en cache : seuls les GET, et seulement une liste fermée de points d'accès.
 * - La déconnexion vide les données en cache (message « oscar:clear »).
 */
const SHELL = 'oscar-shell-v1'
const DATA = 'oscar-data-v1'
const READABLE = [
  /^\/api\/(tasks|suivis|mails|notifications|settings|connections|log|calendar-events|calendar-accounts|mail-accounts|shared-mailboxes|follow-rules|directory\/status)(\/|$|\?)/,
  /^\/api\/mails\/[^/]+\/(body|hints|attachments|invitation)$/,
  /^\/api\/me$/,
]

self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) if (k !== SHELL && k !== DATA) await caches.delete(k)
    await self.clients.claim()
  })())
})

self.addEventListener('message', (e) => {
  if (e.data === 'oscar:clear') e.waitUntil(caches.delete(DATA))
})

async function networkFirst(request, cacheName) {
  const cache = await caches.open(cacheName)
  try {
    const res = await fetch(request)
    if (res.ok) await cache.put(request, res.clone())
    return res
  } catch (err) {
    const hit = await cache.match(request)
    if (hit) return hit
    throw err
  }
}

async function cacheFirst(request) {
  const cache = await caches.open(SHELL)
  const hit = await cache.match(request)
  if (hit) return hit
  const res = await fetch(request)
  if (res.ok) await cache.put(request, res.clone())
  return res
}

self.addEventListener('fetch', (e) => {
  const req = e.request
  if (req.method !== 'GET') return
  const url = new URL(req.url)
  if (url.origin !== self.location.origin) return
  if (req.mode === 'navigate') {
    e.respondWith(networkFirst(new Request('/'), SHELL).catch(() => new Response('Hors ligne', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } })))
    return
  }
  if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest') {
    e.respondWith(cacheFirst(req))
    return
  }
  if (READABLE.some((re) => re.test(url.pathname + url.search))) e.respondWith(networkFirst(req, DATA))
})
