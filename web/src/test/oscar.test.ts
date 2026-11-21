import { describe, expect, it } from 'vitest'
import en from '../i18n/en'
import fr from '../i18n/fr'
import { ERROR_SITUATIONS, INFO_SITUATIONS, validateLine, type Locale } from '../oscar/rules'
import { SITUATIONS, isError } from '../oscar/situations'

type Catalogue = Record<string, { neutral?: string; light?: string }>
const CATALOGUES: [Locale, Catalogue][] = [['fr', fr.oscar as unknown as Catalogue], ['en', en.oscar as unknown as Catalogue]]
const INFO = new Set<string>([...INFO_SITUATIONS, 'sentFollow', 'neverSend'])

describe('la voix d’Oscar : charte de ton vérifiée sur tout le catalogue', () => {
  for (const [locale, cat] of CATALOGUES) {
    describe(locale, () => {
      it('chaque situation existe, avec au moins la version sobre', () => {
        for (const s of SITUATIONS) expect(cat[s]?.neutral, `${locale}.${s}.neutral`).toBeTruthy()
      })

      it('une situation d’erreur n’a jamais de version « légère » : l’humour disparaît', () => {
        for (const s of ERROR_SITUATIONS) expect(cat[s].light, `${locale}.${s}`).toBeUndefined()
      })

      for (const s of SITUATIONS) {
        for (const tone of ['neutral', 'light'] as const) {
          it(`${s} (${tone}) respecte les règles de ton`, () => {
            const line = cat[s]?.[tone]
            if (!line) return
            expect(validateLine(line, locale, { error: isError(s), info: INFO.has(s) })).toEqual([])
          })
        }
      }
    })
  }

  it('le validateur détecte bien les infractions (il n’est pas complaisant)', () => {
    const bad = (line: string, o = { error: false, info: false }) => validateLine(line, 'fr', o).map((v) => v.rule).join(' | ')
    expect(bad('Vous avez oublié de répondre.')).toContain('vouvoiement')
    expect(bad('Tu as oublié de répondre à Sophie. Réessaie.')).toContain('formulation interdite')
    expect(bad('Malheureusement la connexion a échoué. Réessaie.')).toContain('formulation interdite')
    expect(bad('Oups, ça a planté, le plateau est à toi. Réessaie.', { error: true, info: false })).toContain('humour dans une erreur')
    expect(bad('Un message sans aucune proposition.')).toContain('aucune action proposée')
    expect(bad('Un. Deux. Trois. Quatre.', { error: false, info: true })).toContain('plus de 3 phrases')
    expect(bad('Voici une phrase vraiment beaucoup trop longue qui n’en finit plus de continuer encore et encore sans jamais s’arrêter ni reprendre son souffle ?')).toContain('phrase de plus')
    expect(bad('Rien à faire.', { error: false, info: true })).toBe('')
  })

  it('reprend mot pour mot les exemples validés du cahier des charges (§5)', () => {
    expect(fr.oscar.relanceDue.light).toContain('n’a pas donné signe de vie depuis {n} jours. J’ai préparé une relance, tu la relis ?')
    expect(fr.oscar.noMeeting.light).toBe('Plus de rendez-vous aujourd’hui. Le plateau est à toi.')
    expect(fr.oscar.availability.neutral).toBe('{who} demande tes disponibilités. J’ai trouvé {n} créneaux, tu choisis ?')
  })
})
