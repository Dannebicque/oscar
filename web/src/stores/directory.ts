import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../api'
import { searchDirectory, type DirectoryEntry } from '../domain/directory'

/**
 * Annuaire des destinataires (F-18) : celui de l'établissement (LDAP) s'il est configuré sur le serveur, sinon la liste de démonstration.
 * Recherche différée : un appel par pause de frappe, la dernière réponse seule compte.
 */
export const useDirectoryStore = defineStore('directory', () => {
  const configured = ref(false)
  const loaded = ref(false)
  const results = ref<DirectoryEntry[]>([])
  const failed = ref(false)
  let timer: ReturnType<typeof setTimeout> | undefined
  let last = ''

  async function load() {
    if (loaded.value) return
    loaded.value = true
    configured.value = await api.directory.status().then((s) => s.configured).catch(() => false)
  }
  /** Lance la recherche ; sans annuaire d'établissement, la liste locale répond tout de suite. */
  function lookup(q: string) {
    last = q
    clearTimeout(timer)
    failed.value = false
    if (!configured.value) { results.value = searchDirectory(q); return }
    if (q.trim().length < 2) { results.value = []; return }
    timer = setTimeout(async () => {
      try {
        const r = await api.directory.search(q)
        if (q === last) results.value = r
      } catch { if (q === last) { results.value = []; failed.value = true } }
    }, 200)
  }
  return { configured, loaded, results, failed, load, lookup }
})
