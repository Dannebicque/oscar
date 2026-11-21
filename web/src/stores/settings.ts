import { defineStore } from 'pinia'
import { reactive, watch } from 'vue'
import { api, apiMode } from '../api'
import type { ServerSettings } from '../api/types'
import { setLocale, type Locale } from '../i18n'
import { applyAppearance, DENSITIES, isHexAccent, isPaletteId, isStyleId, isThemePreference, type Density, type PaletteId, type StyleId, type ThemePreference } from '../themes'

export type AiMode = 'university' | 'external' | 'off'

export interface Settings {
  locale: Locale
  theme: ThemePreference
  /** Style visuel (forme, relief, typographie) et palette d'accent ; `customAccent` sert quand palette = « custom ». */
  style: StyleId
  palette: PaletteId
  customAccent: string
  density: Density
  aiMode: AiMode
  /** Délai avant relance, en heures (F-54). */
  relanceHours: 24 | 48 | 72 | 168
  businessDays: boolean
  /** Plafond quotidien de relances PROPOSÉES (F-63). */
  dailyCap: 3 | 5 | 10
  recapMorning: string
  recapEvening: string
  recapEmail: boolean
  recapHome: boolean
  includePersonalCalendar: boolean
  shareBusy: boolean
  aiSuggestFollow: boolean
  aiSuggestTasks: boolean
  aiDraftRelance: boolean
  /** Canaux par type de notification (F-75) : { type: { app, desktop, email } } ; ce qui manque prend la valeur par défaut. */
  notifyChannels: Record<string, Record<string, boolean>>
  /** Annuaire (F-18) : champs affichés sous le nom. */
  dirShowDepartment: boolean
  dirShowPhone: boolean
  /** Ton d'Oscar : léger (pointe d'humour) ou sobre. L'humour disparaît toujours dans une erreur. */
  oscarTone: 'light' | 'neutral'
  /** Délai d'annulation de l'envoi, en secondes (0 = envoi immédiat) (F-73). */
  undoSendSeconds: 0 | 5 | 10 | 20 | 30
  /** Raccourcis clavier à une touche : désactivables (WCAG 2.1.4). */
  shortcuts: boolean
}

export const DEFAULTS: Settings = {
  locale: 'fr', theme: 'system', style: 'modern', palette: 'azur', customAccent: '#2457d6', density: 'normal', oscarTone: 'light', shortcuts: true, undoSendSeconds: 10, aiMode: 'university', relanceHours: 48, businessDays: true, dailyCap: 5,
  recapMorning: '08:00', recapEvening: '17:30', recapEmail: true, recapHome: true, includePersonalCalendar: false,
  shareBusy: false, aiSuggestFollow: true, aiSuggestTasks: true, aiDraftRelance: true, notifyChannels: {}, dirShowDepartment: true, dirShowPhone: false,
}

const KEY = 'oscar.settings'

/** Réglages que le serveur applique (relances, récaps, mode d'IA). Les autres restent sur l'appareil. */
export const SERVER_KEYS = [
  'relanceHours', 'businessDays', 'dailyCap', 'recapMorning', 'recapEvening', 'recapEmail', 'recapHome', 'includePersonalCalendar',
  'shareBusy', 'aiMode', 'aiSuggestFollow', 'aiSuggestTasks', 'aiDraftRelance', 'notifyChannels', 'dirShowDepartment', 'dirShowPhone', 'locale',
] as const

/** Fusionne des données stockées (non fiables) avec les valeurs par défaut. */
export function parseSettings(raw: string | null): Settings {
  const out: Settings = { ...DEFAULTS, notifyChannels: {} }
  if (!raw) return out
  try {
    const v = JSON.parse(raw) as Record<string, unknown>
    for (const k of Object.keys(DEFAULTS) as (keyof Settings)[]) {
      if (typeof v[k] === typeof DEFAULTS[k]) (out as unknown as Record<string, unknown>)[k] = v[k]
    }
    const nc = out.notifyChannels as unknown
    out.notifyChannels = nc && typeof nc === 'object' && !Array.isArray(nc) ? JSON.parse(JSON.stringify(nc)) : {}
    if (!isThemePreference(out.theme)) out.theme = DEFAULTS.theme
    if (!isStyleId(out.style)) out.style = DEFAULTS.style
    if (!isPaletteId(out.palette)) out.palette = DEFAULTS.palette
    if (!isHexAccent(out.customAccent)) out.customAccent = DEFAULTS.customAccent
    if (!DENSITIES.includes(out.density)) out.density = DEFAULTS.density
    if (out.locale !== 'fr' && out.locale !== 'en') out.locale = DEFAULTS.locale
    if (!['university', 'external', 'off'].includes(out.aiMode)) out.aiMode = DEFAULTS.aiMode
    if (![0, 5, 10, 20, 30].includes(out.undoSendSeconds)) out.undoSendSeconds = DEFAULTS.undoSendSeconds
    if (!['light', 'neutral'].includes(out.oscarTone)) out.oscarTone = DEFAULTS.oscarTone
  } catch {
    /* données corrompues : valeurs par défaut */
  }
  return out
}

function read(): string | null {
  try { return localStorage.getItem(KEY) } catch { return null }
}

export const useSettingsStore = defineStore('settings', () => {
  const s = reactive<Settings>(parseSettings(read()))
  const media = typeof matchMedia === 'function' ? matchMedia('(prefers-color-scheme: dark)') : null

  function apply() {
    applyAppearance(document.documentElement, s, media?.matches ?? false)
    setLocale(s.locale)
  }
  apply()
  media?.addEventListener?.('change', apply)
  let syncing = false
  let timer: ReturnType<typeof setTimeout> | undefined
  const serverValues = () => Object.fromEntries(SERVER_KEYS.map((k) => [k, s[k]])) as ServerSettings

  watch(s, () => {
    apply()
    try { localStorage.setItem(KEY, JSON.stringify(s)) } catch { /* stockage indisponible */ }
    if (apiMode === 'http' && !syncing) {
      // Un seul envoi après une rafale de changements ; le serveur valide chaque valeur.
      clearTimeout(timer)
      timer = setTimeout(() => { void api.settings.update({ ...serverValues(), timezone: Intl.DateTimeFormat().resolvedOptions().timeZone }).catch(() => undefined) }, 400)
    }
  }, { deep: true })

  /** Après connexion : les réglages du serveur font foi, l'appareil garde thème et densité. */
  async function loadFromServer() {
    try {
      const remote = await api.settings.get()
      syncing = true
      for (const k of SERVER_KEYS) if (k in remote && typeof remote[k] === typeof s[k]) (s as unknown as Record<string, unknown>)[k] = remote[k]
    } catch { /* réglages locaux conservés */ } finally {
      await Promise.resolve()
      syncing = false
    }
  }

  return { s, loadFromServer }
})
