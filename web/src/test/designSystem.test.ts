import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * Garde-fous du design system : l'aspect des cartes, bandeaux, lignes, pastilles et boutons vit dans styles/components.css,
 * et les couleurs ne s'écrivent que par jetons. Un composant qui redéfinit ces classes ou code une couleur en dur casserait
 * l'harmonie entre styles et palettes : ce test l'empêche.
 */
const SRC = join(__dirname, '..')
function files(dir: string, ext: string, out: string[] = []): string[] {
  for (const f of readdirSync(dir)) {
    const p = join(dir, f)
    if (statSync(p).isDirectory()) files(p, ext, out)
    else if (p.endsWith(ext)) out.push(p)
  }
  return out
}
const vue = files(join(SRC), '.vue').filter((f) => !f.includes('/test/'))
const style = (src: string) => [...src.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)].map((m) => m[1]).join('\n')
const rel = (f: string) => f.replace(SRC + '/', '')

describe('design system', () => {
  it('aucun composant ne redéfinit .card, .callout, .chip, .btn, .row ni .panel', () => {
    const offenders = vue.filter((f) => /(^|[\s,}])\.(card|callout|chip|btn|row|panel)\s*(\{|,|:)/m.test(style(readFileSync(f, 'utf8')).replace(/\.\w+\.(card|row)/g, '')))
    expect(offenders.map(rel)).toEqual([])
  })

  it('aucune couleur en dur dans les composants : uniquement des jetons', () => {
    const allowed = new Set(['components/settings/AppearancePanel.vue']) // calcul du contraste, pas un style
    const offenders = vue
      .filter((f) => !allowed.has(rel(f)))
      .filter((f) => /#[0-9a-fA-F]{3,8}\b|rgb\(|rgba\(|hsl\(/.test(style(readFileSync(f, 'utf8')).replace(/color-mix\([^)]*\)/g, '').replace(/hsl\(var\(--av-h\)[^)]*\)/g, '')))
    expect(offenders.map(rel)).toEqual([])
  })

  it('les polices ne se codent pas en dur : le style choisit la police', () => {
    const offenders = vue.filter((f) => /font-family\s*:\s*(?!var\(|inherit)\S/.test(style(readFileSync(f, 'utf8'))))
    expect(offenders.map(rel).filter((f) => f !== 'components/settings/AppearancePanel.vue')).toEqual([])
  })

  it('les feuilles partagées existent et définissent les briques communes', () => {
    const css = readFileSync(join(SRC, 'styles/components.css'), 'utf8')
    for (const sel of ['.card', '.callout', '.row', '.chip', '.btn', '.panel', '.kbd']) expect(css).toContain(sel + ' ')
  })
})
