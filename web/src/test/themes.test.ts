import { describe, expect, it } from 'vitest'
import { contrast, ensureContrast, hexToRgb, mix, readableOn } from '../themes/color'
import { buildTokens, CONTRAST_RULES, failingContrasts, PALETTE_IDS, STYLES, type ResolvedMode, type StyleId } from '../themes/engine'

const MODES: ResolvedMode[] = ['light', 'dark', 'contrast']
const CUSTOM = ['#ffff00', '#000000', '#ffffff', '#888888', '#ff00ff', '#123456', '#00ff7f', '#ff4500', '#7f7f7f', '#2457d6', '#f0e68c', '#191970']

describe('utilitaires de couleur', () => {
  it('calcule le contraste WCAG', () => {
    expect(contrast('#000000', '#ffffff')).toBeCloseTo(21, 0)
    expect(contrast('#ffffff', '#ffffff')).toBeCloseTo(1, 5)
    expect(contrast('#777777', '#ffffff')).toBeCloseTo(4.48, 1)
  })
  it('corrige une couleur trop pâle sans changer sa teinte', () => {
    const fixed = ensureContrast('#ffee88', '#ffffff', 4.5)
    expect(contrast(fixed, '#ffffff')).toBeGreaterThanOrEqual(4.5)
    const [r, g] = hexToRgb(fixed)
    expect(r).toBeGreaterThan(g * 0.8) // reste jaune-ambre
    expect(ensureContrast('#000000', '#ffffff', 4.5)).toBe('#000000')
  })
  it('éclaircit sur un fond sombre', () => {
    expect(contrast(ensureContrast('#222266', '#0e1420', 4.5), '#0e1420')).toBeGreaterThanOrEqual(4.5)
  })
  it('choisit blanc ou noir selon le fond, et mélange', () => {
    expect(readableOn('#ffffff')).toBe('#0b1020')
    expect(readableOn('#000000')).toBe('#ffffff')
    expect(mix('#000000', '#ffffff', 0.5)).toBe('#808080')
  })
})

describe('thèmes : chaque combinaison respecte WCAG AA', () => {
  for (const style of STYLES.map((s) => s.id)) {
    for (const palette of PALETTE_IDS.filter((p) => p !== 'custom')) {
      for (const mode of MODES) {
        it(`${style} · ${palette} · ${mode}`, () => {
          expect(failingContrasts(buildTokens({ style, palette, mode }))).toEqual([])
        })
      }
    }
  }
  for (const hex of CUSTOM) {
    for (const mode of MODES) {
      it(`accent libre ${hex} · ${mode} (corrigé automatiquement)`, () => {
        for (const style of ['modern', 'retro', 'neon'] as StyleId[]) {
          expect(failingContrasts(buildTokens({ style, palette: 'custom', mode, customAccent: hex }))).toEqual([])
        }
      })
    }
  }
  it('un accent invalide retombe sur la palette par défaut', () => {
    expect(buildTokens({ style: 'modern', palette: 'custom', mode: 'light', customAccent: 'pas-une-couleur' })['--c-primary'])
      .toBe(buildTokens({ style: 'modern', palette: 'azur', mode: 'light' })['--c-primary'])
  })
  it('le violet reste réservé à l’IA : aucune palette ne l’utilise', () => {
    for (const p of PALETTE_IDS.filter((x) => x !== 'custom')) {
      const [r, , b] = hexToRgb(buildTokens({ style: 'modern', palette: p, mode: 'light' })['--c-primary'])
      expect(b > r * 1.4 && b > 150 && r > 60 && p !== 'azur').toBe(false)
    }
  })
  it('les styles changent la forme, pas seulement les couleurs', () => {
    const t = (style: StyleId) => buildTokens({ style, palette: 'azur', mode: 'light' })
    expect(t('modern')['--radius-md']).not.toBe(t('retro')['--radius-md'])
    expect(t('retro')['--bw']).toBe('2px')
    expect(t('neon')['--font-sans']).toContain('Space Grotesk')
    expect(CONTRAST_RULES.length).toBeGreaterThan(20)
  })
})
