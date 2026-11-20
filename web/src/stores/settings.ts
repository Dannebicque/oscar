import { defineStore } from 'pinia'
import { reactive, watch } from 'vue'
import { setLocale, type Locale } from '../i18n'
import { applyAppearance, DENSITIES, isThemePreference, type Density, type ThemePreference } from '../themes'

export type AiMode = 'university' | 'external' | 'off'

export interface Settings {
  locale: Locale
  theme: ThemePreference
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
}

export const DEFAULTS: Settings = {
  locale: 'fr', theme: 'system', density: 'normal', aiMode: 'university', relanceHours: 48, businessDays: true, dailyCap: 5,
  recapMorning: '08:00', recapEvening: '17:30', recapEmail: true, recapHome: true, includePersonalCalendar: false,
  shareBusy: false, aiSuggestFollow: true, aiSuggestTasks: true, aiDraftRelance: true,
}

const KEY = 'oscar.settings'

/** Fusionne des données stockées (non fiables) avec les valeurs par défaut. */
export function parseSettings(raw: string | null): Settings {
  const out: Settings = { ...DEFAULTS }
  if (!raw) return out
  try {
    const v = JSON.parse(raw) as Record<string, unknown>
    for (const k of Object.keys(DEFAULTS) as (keyof Settings)[]) {
      if (typeof v[k] === typeof DEFAULTS[k]) (out as unknown as Record<string, unknown>)[k] = v[k]
    }
    if (!isThemePreference(out.theme)) out.theme = DEFAULTS.theme
    if (!DENSITIES.includes(out.density)) out.density = DEFAULTS.density
    if (out.locale !== 'fr' && out.locale !== 'en') out.locale = DEFAULTS.locale
    if (!['university', 'external', 'off'].includes(out.aiMode)) out.aiMode = DEFAULTS.aiMode
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
    applyAppearance(document.documentElement, s.theme, s.density, media?.matches ?? false)
    setLocale(s.locale)
  }
  apply()
  media?.addEventListener?.('change', apply)
  watch(s, () => {
    apply()
    try { localStorage.setItem(KEY, JSON.stringify(s)) } catch { /* stockage indisponible */ }
  }, { deep: true })

  return { s }
})
