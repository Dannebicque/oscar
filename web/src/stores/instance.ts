import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, apiMode } from '../api'
import type { InstanceInfo } from '../domain/types'

/** Ce que l'instance affiche et autorise : nom, inscriptions, limites, et si l'assistant d'installation doit s'ouvrir. */
export const useInstanceStore = defineStore('instance', () => {
  const info = ref<InstanceInfo | null>(null)
  const loaded = ref(apiMode === 'mock')
  const name = computed(() => info.value?.name || 'Oscar')
  const setupNeeded = computed(() => info.value?.setupNeeded === true)

  async function load() {
    try { info.value = await api.instance.info() } catch { /* serveur injoignable : on garde les valeurs par défaut, la connexion le dira */ } finally { loaded.value = true }
  }
  return { info, loaded, name, setupNeeded, load }
})
