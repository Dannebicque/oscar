import { createI18n } from 'vue-i18n'
import en from './en'
import fr from './fr'

export const LOCALES = ['fr', 'en'] as const
export type Locale = (typeof LOCALES)[number]

const KEY = 'oscar.locale'

function initial(): Locale {
  try {
    const saved = localStorage.getItem(KEY)
    if (saved === 'fr' || saved === 'en') return saved
  } catch {
    /* stockage indisponible */
  }
  return 'fr'
}

export const i18n = createI18n({ legacy: false, locale: initial(), fallbackLocale: 'fr', messages: { fr, en } })

export function setLocale(l: Locale) {
  i18n.global.locale.value = l
  document.documentElement.lang = l
  try {
    localStorage.setItem(KEY, l)
  } catch {
    /* ignoré */
  }
}
