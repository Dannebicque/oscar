import { createI18n } from 'vue-i18n'
import en from './en'
import fr from './fr'

export const LOCALES = ['fr', 'en'] as const
export type Locale = (typeof LOCALES)[number]

export const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'fr', messages: { fr, en } })

export function setLocale(l: Locale) {
  i18n.global.locale.value = l
  document.documentElement.lang = l
}
