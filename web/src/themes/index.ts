/**
 * Registre des thèmes. Ajouter un thème = une entrée ici, un bloc [data-theme] dans styles/themes.css
 * et un libellé `settings.themes.<id>` dans i18n. Aucun composant à modifier (U-09).
 */
export const THEMES = [
  { id: 'system', icon: 'monitor' },
  { id: 'light', icon: 'sun' },
  { id: 'dark', icon: 'moon' },
  { id: 'contrast', icon: 'contrast' },
] as const

export type ThemePreference = (typeof THEMES)[number]['id']
export type ResolvedTheme = Exclude<ThemePreference, 'system'>

export function isThemePreference(v: unknown): v is ThemePreference {
  return THEMES.some((t) => t.id === v)
}

export function resolveTheme(pref: ThemePreference, prefersDark: boolean): ResolvedTheme {
  return pref === 'system' ? (prefersDark ? 'dark' : 'light') : pref
}

export const DENSITIES = ['comfortable', 'normal', 'compact'] as const
export type Density = (typeof DENSITIES)[number]

export function applyAppearance(root: HTMLElement, pref: ThemePreference, density: Density, prefersDark: boolean) {
  root.dataset.theme = resolveTheme(pref, prefersDark)
  root.dataset.density = density
}
