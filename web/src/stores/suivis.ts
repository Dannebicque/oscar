import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api/suivis'
import { needsAction, sortByPriority } from '../domain/priority'
import type { Suivi } from '../domain/types'

export const useSuivisStore = defineStore('suivis', () => {
  const items = ref<Suivi[]>([])
  const loading = ref(false)

  const sorted = computed(() => sortByPriority(items.value))
  const actionCount = computed(() => items.value.filter(needsAction).length)

  async function load() {
    loading.value = true
    try {
      items.value = await api.list()
    } finally {
      loading.value = false
    }
  }

  async function close(id: string) {
    await api.close(id)
    await load()
  }

  return { items, loading, sorted, actionCount, load, close }
})
