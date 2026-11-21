import { useI18n } from 'vue-i18n'
import { useSettingsStore } from '../stores/settings'
import { isError, type OscarTone, type Situation } from './situations'

/**
 * La voix d'Oscar : un seul point d'entrée pour tout ce qu'il dit. Choisit la version légère ou sobre selon le réglage de
 * l'utilisateur, et impose le ton sobre dans toute situation d'erreur, quoi que dise le réglage.
 */
export function useOscar() {
  const { t, te } = useI18n()
  const settings = useSettingsStore()

  function toneFor(situation: Situation, forced?: OscarTone): OscarTone {
    if (isError(situation)) return 'neutral'
    return forced ?? settings.s.oscarTone
  }

  /** Phrase d'Oscar pour une situation, dans la langue de l'interface. */
  function say(situation: Situation, params: Record<string, string | number> = {}, forced?: OscarTone): string {
    const tone = toneFor(situation, forced)
    const key = `oscar.${situation}.${tone}`
    return t(te(key) ? key : `oscar.${situation}.neutral`, params)
  }
  return { say, toneFor }
}
