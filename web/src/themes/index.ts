import { applyTokens, buildTokens, isHexAccent, PALETTE_IDS, STYLES, type PaletteId, type StyleId } from './engine'

/**
 * Registre des thèmes. Un thème = un style (forme, relief, typographie) × une palette (accent) × un mode.
 * Ajouter un style : une entrée dans `engine.ts` (STYLES + styleTokens + neutrals) et un libellé `settings.look.styles.<id>`.
 * Ajouter une palette : une entrée dans PALETTES et un libellé `settings.look.palettes.<id>`. Les tests vérifient les contrastes.
 */
export const THEMES = [
  { id: 'system', icon: 'monitor' },
  { id: 'light', icon: 'sun' },
  { id: 'dark', icon: 'moon' },
  { id: 'contrast', icon: 'contrast' },
] as const

export type ThemePreference = (typeof THEMES)[number]['id']
export type ResolvedTheme = Exclude<ThemePreference, 'system'>

export { PALETTE_IDS, STYLES }
export type { PaletteId, StyleId }

export const isThemePreference = (v: unknown): v is ThemePreference => THEMES.some((t) => t.id === v)
export const isStyleId = (v: unknown): v is StyleId => STYLES.some((s) => s.id === v)
export const isPaletteId = (v: unknown): v is PaletteId => PALETTE_IDS.includes(v as PaletteId)
export { isHexAccent }

export function resolveTheme(pref: ThemePreference, prefersDark: boolean): ResolvedTheme {
  return pref === 'system' ? (prefersDark ? 'dark' : 'light') : pref
}

export const DENSITIES = ['comfortable', 'normal', 'compact'] as const
export type Density = (typeof DENSITIES)[number]

export interface Appearance { theme: ThemePreference; style: StyleId; palette: PaletteId; customAccent: string; density: Density }

export function applyAppearance(root: HTMLElement, a: Appearance, prefersDark: boolean) {
  const mode = resolveTheme(a.theme, prefersDark)
  applyTokens(root, buildTokens({ style: a.style, palette: a.palette, mode, customAccent: a.customAccent }))
  root.dataset.theme = mode
  root.dataset.style = a.style
  root.dataset.palette = a.palette
  root.dataset.density = a.density
}
