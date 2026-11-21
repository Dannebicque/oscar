/**
 * Règles de ton d'Oscar (cahier des charges §5) : phrases courtes, tutoiement, jamais de reproche, humour léger qui
 * disparaît dès qu'il s'agit d'une erreur, et une action proposée. Ces règles sont vérifiées par test sur TOUT le catalogue
 * (`test/oscar.test.ts`) : une phrase qui les enfreint ne passe pas la CI.
 */
export type Locale = 'fr' | 'en'

export const MAX_WORDS_PER_SENTENCE = 18
export const MAX_SENTENCES = 3

/** Reproches, culpabilisation, jargon technique : jamais dans la bouche d'Oscar. */
export const FORBIDDEN: Record<Locale, RegExp[]> = {
  fr: [/\btu as oubli[ée]/i, /\btu aurais d[uû]/i, /\bc['’]est (de )?ta faute/i, /\bmalheureusement\b/i, /\btu n['’]as pas\b/i, /\bencore\b.*\b(oubli|retard)/i, /\bexception\b/i, /\bstack\b/i, /\berreur \d{3}\b/i, /\bnull\b|\bundefined\b/i],
  en: [/\byou forgot\b/i, /\byou should have\b/i, /\bit['’]s your fault\b/i, /\bunfortunately\b/i, /\byou didn['’]t\b/i, /\bexception\b/i, /\bstack\b/i, /\berror \d{3}\b/i, /\bnull\b|\bundefined\b/i],
}

/** Vouvoiement interdit en français : Oscar tutoie (dans l'application, hors baseline de marque). */
export const NO_VOUS: RegExp = /(?<!rendez-)\b(vous|votre|vos|veuillez)\b/i

/** Marqueurs d'humour : interdits dans une situation d'erreur (le message est alors d'abord clair). */
export const HUMOR: Record<Locale, RegExp[]> = {
  fr: [/\bplateau est à toi\b/i, /\bsouffler\b/i, /\bun de moins\b/i, /\bmajordome\b/i, /\bà ton service\b/i, /\bsigne de vie\b/i, /!/],
  en: [/\bfloor is yours\b/i, /\bbreathe\b/i, /\bone less\b/i, /\bbutler\b/i, /\bat your service\b/i, /\bsign of life\b/i, /!/],
}

/** Une phrase d'Oscar propose toujours quelque chose : un verbe d'action à l'impératif ou une question qui invite à choisir. */
const ACTION: Record<Locale, RegExp> = {
  fr: /\?|\b(réessaie|vérifie|relis|ajoute[sz]?|choisis|ouvre|valides?|décides?|prends|corrige|reconnecte|regarde[sz]?|passe|dis-moi|tu peux)\b/i,
  en: /\?|\b(try|check|review|add|choose|open|confirm|decide|take|fix|reconnect|look|you can|tell me)\b/i,
}

const sentences = (s: string) => s.split(/(?<=[.!?…])\s+/).map((x) => x.trim()).filter(Boolean)
const words = (s: string) => s.replace(/\{[^}]*\}/g, 'x').split(/\s+/).filter(Boolean).length

/** Situations d'erreur : le ton léger n'existe pas. */
export const ERROR_SITUATIONS = ['errorGeneric', 'errorConnection', 'errorSend'] as const
/** Situations purement informatives : elles n'ont pas à proposer d'action. */
export const INFO_SITUATIONS = ['greeting', 'quiet', 'calm', 'noMeeting', 'closed', 'neverSend', 'recapIntro', 'neverDeletes'] as const

export interface Violation { rule: string; line: string }

export function validateLine(line: string, locale: Locale, opts: { error: boolean; info: boolean }): Violation[] {
  const out: Violation[] = []
  const add = (rule: string) => out.push({ rule, line })
  const ss = sentences(line)
  if (ss.length > MAX_SENTENCES) add(`plus de ${MAX_SENTENCES} phrases`)
  for (const s of ss) if (words(s) > MAX_WORDS_PER_SENTENCE) add(`phrase de plus de ${MAX_WORDS_PER_SENTENCE} mots : « ${s.slice(0, 40)}… »`)
  for (const re of FORBIDDEN[locale]) if (re.test(line)) add(`formulation interdite (${re})`)
  if (locale === 'fr' && NO_VOUS.test(line)) add('vouvoiement : Oscar tutoie')
  if (opts.error) for (const re of HUMOR[locale]) if (re.test(line)) add(`humour dans une erreur (${re})`)
  if (!opts.info && !ACTION[locale].test(line)) add('aucune action proposée')
  return out
}
