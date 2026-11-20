import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import en from '../i18n/en'
import fr from '../i18n/fr'

type Tree = { [k: string]: string | Tree }
function leaves(t: Tree, prefix = ''): string[] {
  return Object.entries(t).flatMap(([k, v]) => (typeof v === 'string' ? [`${prefix}${k}`] : leaves(v, `${prefix}${k}.`)))
}

describe('i18n', () => {
  const keysFr = leaves(fr as unknown as Tree)
  it('fr et en ont exactement les mêmes clés (N-10)', () => {
    expect(leaves(en as unknown as Tree).sort()).toEqual([...keysFr].sort())
  })
  for (const [locale, messages] of [['fr', fr], ['en', en]] as const) {
    it(`tous les messages ${locale} se compilent (pas de @ ou { non échappé)`, () => {
      const i18n = createI18n({ legacy: false, locale, messages: { [locale]: messages }, missingWarn: false, warnHtmlMessage: false })
      const errors: string[] = []
      for (const key of keysFr) {
        try { i18n.global.t(key, { n: 1, who: 'x', date: 'x', subject: 'x', title: 'x', first: 'x', me: 'x' }, 1) } catch (e) { errors.push(`${key}: ${(e as Error).message}`) }
      }
      expect(errors).toEqual([])
    })
  }
})
