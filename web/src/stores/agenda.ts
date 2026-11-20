import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api'
import { nextEvent } from '../domain/agenda'
import { clock } from '../domain/clock'
import type { CalEvent, CalendarSource } from '../domain/types'
import { useSettingsStore } from './settings'

export const useAgendaStore = defineStore('agenda', () => {
  const events = ref<CalEvent[]>([])
  const sources = ref<CalendarSource[]>([])
  const loading = ref(false)
  const error = ref(false)

  const next = computed(() => nextEvent(events.value, sources.value, clock.now(), useSettingsStore().s.includePersonalCalendar))
  /** Rendez-vous « pro » : l'agenda perso reste hors des priorités (F-71). */
  const work = computed(() => {
    const personal = new Set(sources.value.filter((s) => s.personal).map((s) => s.id))
    return events.value.filter((e) => !personal.has(e.calendar))
  })
  const sourceOf = (id: string) => sources.value.find((s) => s.id === id)

  async function load() {
    loading.value = true
    error.value = false
    try {
      ;[sources.value, events.value] = await Promise.all([api.agenda.sources(), api.agenda.events()])
    } catch { error.value = true } finally { loading.value = false }
  }
  async function add(e: Omit<CalEvent, 'id'>) {
    events.value = [...events.value, await api.agenda.create(e)]
  }
  return { events, sources, loading, error, next, work, sourceOf, load, add }
})
