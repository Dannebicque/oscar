import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api'
import { nextEvent } from '../domain/agenda'
import { clock } from '../domain/clock'
import type { CalEvent, CalendarSource, SlotHold } from '../domain/types'
import { useSettingsStore } from './settings'

export const useAgendaStore = defineStore('agenda', () => {
  const events = ref<CalEvent[]>([])
  const sources = ref<CalendarSource[]>([])
  /** Créneaux en provisoire (F-74). */
  const holds = ref<SlotHold[]>([])
  /** Une panne ici n'empêche jamais de voir l'agenda : on garde ce qu'on savait. */
  async function loadHolds() { holds.value = await api.agenda.holds().catch(() => holds.value) }
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
      await loadHolds()
    } catch { error.value = true } finally { loading.value = false }
  }
  async function add(e: Omit<CalEvent, 'id'>) {
    events.value = [...events.value, await api.agenda.create(e)]
  }
  async function releaseHold(id: string) {
    await api.agenda.releaseHold(id)
    holds.value = holds.value.filter((h) => h.id !== id)
  }
  async function releaseGroup(groupId: string) {
    await api.agenda.releaseHoldGroup(groupId)
    holds.value = holds.value.filter((h) => h.groupId !== groupId)
  }
  /** « Retenir » : l'événement est écrit dans l'agenda choisi, et seulement ensuite la proposition est libérée. */
  async function keepHold(h: SlotHold, calendar: string, title: string) {
    await add({ calendar, title, start: h.start, end: h.end, location: null })
    await releaseGroup(h.groupId)
  }
  return { events, sources, holds, loading, error, next, work, sourceOf, load, loadHolds, add, releaseHold, releaseGroup, keepHold }
})
