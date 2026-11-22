import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, session } from '../api'
import type { SuiviPatch } from '../api/types'
import { clock } from '../domain/clock'
import { addDays, isoDay } from '../domain/dates'
import { flagOf, needsAction, relanceQueue, sortByPriority } from '../domain/suivi'
import type { NewSuivi, Status, Suivi } from '../domain/types'
import { useSettingsStore } from './settings'

export type Scope = 'mine' | 'team'

export const useSuivisStore = defineStore('suivis', () => {
  const items = ref<Suivi[]>([])
  const loading = ref(false)
  const error = ref(false)
  const scope = ref<Scope>('mine')

  const inScope = computed(() => items.value.filter((s) => (scope.value === 'team' ? s.mailbox !== 'personal' : s.mailbox === 'personal')))
  const sorted = computed(() => sortByPriority(inScope.value, clock.now()))
  const mine = computed(() => items.value.filter((s) => s.mailbox === 'personal'))
  const actionCount = computed(() => mine.value.filter((s) => needsAction(s, clock.now())).length)
  // Mode congés : aucune relance n'est proposée pendant l'absence ; elles reviennent au retour.
  const relances = computed(() => (useSettingsStore().vacationActive(clock.now()) ? [] : relanceQueue(mine.value, clock.now(), useSettingsStore().s.dailyCap)))
  const byId = (id: string) => items.value.find((s) => s.id === id)

  async function load() {
    loading.value = true
    error.value = false
    try {
      items.value = await api.suivis.list()
    } catch {
      error.value = true
    } finally {
      loading.value = false
    }
  }

  function replace(next: Suivi) {
    items.value = items.value.map((s) => (s.id === next.id ? next : s))
  }

  async function patch(id: string, p: SuiviPatch, action: string) {
    const next = await api.suivis.update(id, p, { at: clock.now().toISOString(), author: session.name, action })
    replace(next)
    return next
  }

  async function create(n: NewSuivi) {
    const s = await api.suivis.create(n)
    items.value = [...items.value, s]
    return s
  }

  /** Clore (jamais automatique, F-65). Renvoie de quoi annuler. */
  async function close(id: string) {
    const before = byId(id)
    if (!before) return undefined
    await api.suivis.close(id)
    await load()
    return () => patch(id, { status: before.status, replyReceived: before.replyReceived }, 'reopened')
  }

  /** Brouillon de relance : celui de l'IA si elle est active, sinon null (le front garde son modèle local). */
  const draftRelance = (id: string) => api.suivis.draftRelance(id)
  const setStatus = (id: string, status: Status) => patch(id, { status }, `status:${status}`)
  /** Réponse reçue mais il reste une action : on garde la demande ouverte (F-65). */
  const keepOpen = (id: string) => patch(id, { replyReceived: false, status: 'inProgress' }, 'keptOpen')
  /** Un bouton pour garder la demande en attente malgré une réponse (F-52). */
  const keepWaiting = (id: string) => patch(id, { replyReceived: false, status: 'waiting' }, 'keptWaiting')
  const stopRelance = (id: string) => patch(id, { noRelance: true, relanceAt: null }, 'noRelance')
  const resumeRelance = (id: string) => patch(id, { noRelance: false }, 'relanceResumed')
  const snooze = (id: string, days = 2) => patch(id, { relanceAt: isoDay(addDays(clock.now(), days)) }, 'snoozed')
  /** L'envoi lui-même est un clic de l'utilisateur ; ici on enregistre qu'il a eu lieu. */
  function relanceSent(id: string) {
    const s = byId(id)
    const hours = useSettingsStore().s.relanceHours
    return patch(id, { relanceCount: (s?.relanceCount ?? 0) + 1, relanceAt: isoDay(addDays(clock.now(), Math.ceil(hours / 24))) }, 'relanceSent')
  }

  return {
    items, loading, error, scope, sorted, inScope, mine, actionCount, relances, load, create, close, setStatus, keepOpen,
    keepWaiting, stopRelance, resumeRelance, snooze, relanceSent, draftRelance, byId, flag: (s: Suivi) => flagOf(s, clock.now()),
  }
})
