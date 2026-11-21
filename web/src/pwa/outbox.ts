/**
 * File d'envoi hors ligne (F-80). Un message écrit sans réseau est gardé ici ; il part au retour de la connexion et son envoi est confirmé.
 * Ce n'est pas un envoi « tout seul » : le message a déjà été envoyé par un clic de l'utilisateur ; la file ne fait que patienter.
 * Le suivi demandé n'est créé que lorsque le message est réellement parti (règle serveur, F-73).
 * IndexedDB si disponible (les pièces jointes peuvent peser jusqu'à 5 Mo), sinon mémoire.
 */
import type { SendPayload } from '../api/types'

export interface QueuedMail { id: string; at: string; payload: SendPayload }

const DB = 'oscar-outbox'
const STORE = 'mails'
let memory: QueuedMail[] = []

const idb = (): IDBFactory | null => (typeof indexedDB !== 'undefined' ? indexedDB : null)

function open(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const req = idb()!.open(DB, 1)
    req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'id' })
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}
const done = (tx: IDBTransaction) => new Promise<void>((ok, ko) => { tx.oncomplete = () => ok(); tx.onerror = () => ko(tx.error); tx.onabort = () => ko(tx.error) })

export async function enqueue(payload: SendPayload): Promise<QueuedMail> {
  const item: QueuedMail = { id: `q${Date.now()}${Math.random().toString(36).slice(2, 6)}`, at: new Date().toISOString(), payload }
  if (!idb()) { memory = [...memory, item]; return item }
  try {
    const db = await open()
    const tx = db.transaction(STORE, 'readwrite')
    tx.objectStore(STORE).put(item)
    await done(tx)
    db.close()
  } catch { memory = [...memory, item] }
  return item
}

export async function pending(): Promise<QueuedMail[]> {
  let stored: QueuedMail[] = []
  if (idb()) {
    try {
      const db = await open()
      stored = await new Promise<QueuedMail[]>((ok, ko) => { const r = db.transaction(STORE).objectStore(STORE).getAll(); r.onsuccess = () => ok(r.result as QueuedMail[]); r.onerror = () => ko(r.error) })
      db.close()
    } catch { stored = [] }
  }
  return [...stored, ...memory].sort((a, b) => a.at.localeCompare(b.at))
}

export async function remove(id: string): Promise<void> {
  memory = memory.filter((m) => m.id !== id)
  if (!idb()) return
  try {
    const db = await open()
    const tx = db.transaction(STORE, 'readwrite')
    tx.objectStore(STORE).delete(id)
    await done(tx)
    db.close()
  } catch { /* déjà absent */ }
}

/** Pour les tests : vide la mémoire. */
export const resetMemoryOutbox = () => { memory = [] }
