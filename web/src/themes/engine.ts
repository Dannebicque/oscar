import { contrast, ensureContrast, isHex, mix, readableOn } from './color'

/**
 * Moteur de thèmes : un thème = un STYLE (forme, relief, typographie) × une PALETTE (accent) × un MODE (clair, sombre,
 * contraste élevé). Tout est calculé ici, en un seul endroit, et vérifié par test : chaque combinaison respecte les
 * contrastes WCAG AA, y compris avec un accent choisi librement (il est corrigé au besoin). Les composants ne lisent que
 * des jetons CSS (--c-*, --radius-*, …).
 */
export type StyleId = 'modern' | 'retro' | 'neon'
export type PaletteId = 'azur' | 'emeraude' | 'ambre' | 'corail' | 'graphite' | 'custom'
export type ResolvedMode = 'light' | 'dark' | 'contrast'

export const STYLES: { id: StyleId; icon: string }[] = [
  { id: 'modern', icon: 'sparkles' }, { id: 'retro', icon: 'draft' }, { id: 'neon', icon: 'sun' },
]
/** Accent de base par mode. Le violet est réservé à l'IA : aucune palette ne l'utilise. */
export const PALETTES: Record<Exclude<PaletteId, 'custom'>, { light: string; dark: string }> = {
  azur: { light: '#2457d6', dark: '#7aa2ff' },
  emeraude: { light: '#047857', dark: '#34d399' },
  ambre: { light: '#b45309', dark: '#fbbf24' },
  corail: { light: '#be123c', dark: '#fb7185' },
  graphite: { light: '#334155', dark: '#cbd5e1' },
}
export const PALETTE_IDS = [...Object.keys(PALETTES), 'custom'] as PaletteId[]

export interface ThemeInput { style: StyleId; palette: PaletteId; mode: ResolvedMode; customAccent?: string }
export type Tokens = Record<string, string>

interface Neutrals { bg: string; surface: string; surface2: string; border: string; borderStrong: string; text: string; muted: string }
interface Status { fg: string; soft: string }

/** Neutres par style et par mode. */
function neutrals(style: StyleId, mode: ResolvedMode): Neutrals {
  if (mode === 'contrast') return { bg: '#000000', surface: '#000000', surface2: '#14141f', border: '#ffffff', borderStrong: '#ffffff', text: '#ffffff', muted: '#f0f0f0' }
  const t: Record<StyleId, Record<'light' | 'dark', Neutrals>> = {
    modern: {
      light: { bg: '#f4f6f9', surface: '#ffffff', surface2: '#eef1f5', border: '#e4e8ee', borderStrong: '#7a8697', text: '#1b2430', muted: '#4f5a69' },
      dark: { bg: '#0e1420', surface: '#161e2c', surface2: '#1f2938', border: '#2b3850', borderStrong: '#8794ac', text: '#eaf0fa', muted: '#aab6c9' },
    },
    // Papier chaud, encre brune, traits épais : l'esprit des interfaces d'avant, lisible et chaleureux.
    retro: {
      light: { bg: '#efe6d2', surface: '#fbf6ea', surface2: '#f3ead6', border: '#2b2118', borderStrong: '#2b2118', text: '#2b2118', muted: '#5b4a39' },
      dark: { bg: '#17130d', surface: '#211b12', surface2: '#2c2418', border: '#d9c9a3', borderStrong: '#d9c9a3', text: '#f4ead2', muted: '#c9b88f' },
    },
    // Nuit profonde, bords lumineux. En clair : fond blanc bleuté et traits vifs, sans halo.
    neon: {
      light: { bg: '#eef0ff', surface: '#ffffff', surface2: '#e6e9ff', border: '#c8cdf5', borderStrong: '#5b5fa8', text: '#14163a', muted: '#454a82' },
      dark: { bg: '#07081a', surface: '#0f1130', surface2: '#171a44', border: '#2c3078', borderStrong: '#8a90e8', text: '#eceeff', muted: '#b3b8f0' },
    },
  }
  return t[style][mode]
}

/** Couleurs de statut (ambre, rouge, vert, sarcelle) et teinte réservée à l'IA, calibrées par mode. */
function statuses(mode: ResolvedMode, n: Neutrals): Record<'warn' | 'danger' | 'success' | 'ai' | 'cal2', Status> {
  const dark = mode !== 'light'
  const base = dark
    ? { warn: '#fdb022', danger: '#ff8a80', success: '#4ade80', ai: '#b9a8ff', cal2: '#4fd1c5' }
    : { warn: '#93370d', danger: '#b42318', success: '#067647', ai: '#4b34b8', cal2: '#0b6e6e' }
  const out = {} as Record<keyof typeof base, Status>
  for (const k of Object.keys(base) as (keyof typeof base)[]) {
    const soft = mode === 'contrast' ? '#000000' : mix(n.surface, base[k], dark ? 0.18 : 0.12)
    out[k] = { fg: ensureContrast(base[k], soft, 4.5), soft }
  }
  return out
}

/** Corrige une couleur pour qu'elle atteigne `target` sur TOUS les fonds donnés (le plus exigeant l'emporte). */
function ensureOnAll(color: string, backgrounds: string[], target: number): string {
  let c = color
  for (let i = 0; i < 4; i++) {
    for (const bg of backgrounds) c = ensureContrast(c, bg, target)
    if (backgrounds.every((bg) => contrast(c, bg) >= target)) break
  }
  return c
}

/**
 * Accent effectif. « base » = fond des boutons principaux (texte lisible dessus, et 3:1 contre la surface) ;
 * « text » = accent utilisé comme texte ou icône (4,5:1 sur tous les fonds de page).
 */
function accentFor(input: ThemeInput, n: Neutrals): { base: string; text: string } {
  if (input.mode === 'contrast') return { base: '#ffe14d', text: '#ffe14d' }
  const pick = input.palette === 'custom' && isHex(input.customAccent) ? input.customAccent : PALETTES[input.palette === 'custom' ? 'azur' : input.palette][input.mode === 'light' ? 'light' : 'dark']
  const text = ensureOnAll(pick, [n.bg, n.surface, n.surface2], 4.5)
  const base = input.mode === 'light' ? ensureContrast(pick, '#ffffff', 4.5) : ensureOnAll(pick, [n.bg, n.surface], 3)
  return { base, text }
}

export function buildTokens(input: ThemeInput): Tokens {
  const n = neutrals(input.style, input.mode)
  const a = accentFor(input, n)
  const s = statuses(input.mode, n)
  const dark = input.mode !== 'light'
  const onPrimary = readableOn(a.base)
  // Le survol s'éloigne de la couleur du texte (texte blanc → fond plus foncé), pour ne jamais perdre en lisibilité.
  const hoverTry = mix(a.base, onPrimary === '#ffffff' ? '#000000' : '#ffffff', 0.15)
  const hover = input.mode === 'contrast' ? '#fff08a' : contrast(onPrimary, hoverTry) >= 4.5 ? hoverTry : a.base
  const softBg = input.mode === 'contrast' ? '#000000' : mix(n.surface, a.base, dark ? 0.2 : 0.1)
  const onSoft = ensureOnAll(a.text, [softBg], 4.5)
  const focus = input.mode === 'contrast' ? '#ffe14d' : ensureContrast(a.base, n.surface, 3)

  const colors: Tokens = {
    '--c-bg': n.bg, '--c-surface': n.surface, '--c-surface-2': n.surface2, '--c-border': n.border, '--c-border-strong': n.borderStrong,
    '--c-text': n.text, '--c-text-muted': n.muted,
    '--c-primary': a.base, '--c-primary-hover': hover, '--c-on-primary': onPrimary,
    '--c-accent': a.text, '--c-primary-soft': softBg, '--c-on-primary-soft': onSoft, '--c-focus': focus,
    '--c-ai': s.ai.fg, '--c-ai-soft': s.ai.soft, '--c-warn': s.warn.fg, '--c-warn-soft': s.warn.soft,
    '--c-danger': s.danger.fg, '--c-danger-soft': s.danger.soft, '--c-success': s.success.fg, '--c-success-soft': s.success.soft,
    '--c-cal2': s.cal2.fg, '--c-cal2-soft': s.cal2.soft, '--c-on-cal2-soft': s.cal2.fg,
    '--c-neutral-soft': n.surface2, '--c-overlay': dark ? 'rgb(0 0 0 / 0.7)' : 'rgb(15 23 42 / 0.5)',
    // Rouge plein pour fonds avec texte blanc (pastille d'alertes, notification d'erreur) : lisible dans tous les modes.
    '--c-danger-solid': '#b42318', '--c-on-danger-solid': '#ffffff',
  }
  return { ...colors, ...styleTokens(input.style, input.mode, a.base, n) }
}

/** Forme, relief et typographie propres à chaque style. */
function styleTokens(style: StyleId, mode: ResolvedMode, accent: string, n: Neutrals): Tokens {
  const high = mode === 'contrast'
  if (style === 'retro') {
    return {
      '--font-sans': "'JetBrains Mono Variable', ui-monospace, 'SF Mono', Menlo, Consolas, monospace", '--font-display': "'JetBrains Mono Variable', ui-monospace, Menlo, monospace",
      '--radius-sm': '3px', '--radius-md': '4px', '--radius-lg': '6px', '--radius-pill': '4px', '--bw': '2px', '--bw-card': '2px',
      '--shadow-1': `2px 2px 0 ${n.border}`, '--shadow-2': `5px 5px 0 ${n.border}`, '--glow': 'none', '--tracking': '0', '--label-case': 'none', '--fs-base-scale': '0.94',
      '--bg-image': mode === 'light' ? `radial-gradient(${mix(n.bg, n.border, 0.13)} 1px, transparent 1px)` : 'none', '--bg-size': '14px 14px', '--hover-lift': '-1px',
    }
  }
  if (style === 'neon') {
    const glow = mode === 'light' ? 'none' : high ? 'none' : `0 0 0 1px ${mix(n.surface, accent, 0.55)}, 0 0 20px ${mix(n.bg, accent, 0.45)}`
    return {
      '--font-sans': "'Space Grotesk Variable', 'Inter Variable', system-ui, sans-serif", '--font-display': "'Space Grotesk Variable', 'Inter Variable', system-ui, sans-serif",
      '--radius-sm': '10px', '--radius-md': '14px', '--radius-lg': '20px', '--radius-pill': '999px', '--bw': '1px', '--bw-card': '1px',
      '--shadow-1': mode === 'light' ? '0 1px 3px rgb(20 22 58 / 0.14)' : `0 0 0 1px ${mix(n.surface, accent, 0.3)}`, '--shadow-2': glow === 'none' ? '0 10px 30px rgb(20 22 58 / 0.25)' : glow,
      '--glow': glow, '--tracking': '0.01em', '--label-case': 'none', '--fs-base-scale': '1',
      '--bg-image': mode === 'dark' ? `radial-gradient(60rem 30rem at 85% -10%, ${mix(n.bg, accent, 0.22)}, transparent 65%), radial-gradient(40rem 24rem at -10% 110%, ${mix(n.bg, '#8b5cf6', 0.18)}, transparent 60%)` : 'none',
      '--bg-size': 'auto', '--hover-lift': '0px',
    }
  }
  return {
    '--font-sans': "'Inter Variable', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", '--font-display': "'Inter Variable', system-ui, sans-serif",
    '--radius-sm': '8px', '--radius-md': '12px', '--radius-lg': '16px', '--radius-pill': '999px', '--bw': '1px', '--bw-card': '1px',
    '--shadow-1': mode === 'light' ? '0 1px 2px rgb(16 24 40 / 0.06), 0 1px 3px rgb(16 24 40 / 0.1)' : '0 1px 2px rgb(0 0 0 / 0.5)',
    '--shadow-2': mode === 'light' ? '0 12px 32px rgb(16 24 40 / 0.18)' : '0 12px 32px rgb(0 0 0 / 0.6)', '--glow': 'none', '--tracking': '-0.005em', '--label-case': 'none',
    '--fs-base-scale': '1', '--bg-image': 'none', '--bg-size': 'auto', '--hover-lift': '0px',
  }
}

/** Paires (premier plan, fond, seuil) qui DOIVENT respecter WCAG 2.2 : vérifiées pour chaque combinaison par les tests. */
export const CONTRAST_RULES: { fg: string; bg: string; min: number; what: string }[] = [
  { fg: '--c-text', bg: '--c-bg', min: 4.5, what: 'texte sur fond' },
  { fg: '--c-text', bg: '--c-surface', min: 4.5, what: 'texte sur surface' },
  { fg: '--c-text', bg: '--c-surface-2', min: 4.5, what: 'texte sur surface secondaire' },
  { fg: '--c-text-muted', bg: '--c-bg', min: 4.5, what: 'texte atténué sur fond' },
  { fg: '--c-text-muted', bg: '--c-surface', min: 4.5, what: 'texte atténué sur surface' },
  { fg: '--c-text-muted', bg: '--c-surface-2', min: 4.5, what: 'texte atténué sur surface secondaire' },
  { fg: '--c-on-primary', bg: '--c-primary', min: 4.5, what: 'texte de bouton principal' },
  { fg: '--c-on-primary', bg: '--c-primary-hover', min: 4.5, what: 'texte de bouton principal au survol' },
  { fg: '--c-accent', bg: '--c-surface', min: 4.5, what: 'liens et accents sur surface' },
  { fg: '--c-accent', bg: '--c-bg', min: 4.5, what: 'liens et accents sur fond' },
  { fg: '--c-on-primary-soft', bg: '--c-primary-soft', min: 4.5, what: 'texte sur teinte d’accent' },
  { fg: '--c-text', bg: '--c-primary-soft', min: 4.5, what: 'texte courant sur teinte d’accent' },
  { fg: '--c-warn', bg: '--c-warn-soft', min: 4.5, what: 'relance due' },
  { fg: '--c-danger', bg: '--c-danger-soft', min: 4.5, what: 'retard, erreur' },
  { fg: '--c-success', bg: '--c-success-soft', min: 4.5, what: 'traité, succès' },
  { fg: '--c-ai', bg: '--c-ai-soft', min: 4.5, what: 'suggestion de l’IA' },
  { fg: '--c-cal2', bg: '--c-cal2-soft', min: 4.5, what: 'agenda perso' },
  { fg: '--c-text', bg: '--c-warn-soft', min: 4.5, what: 'texte sur bandeau de relance' },
  { fg: '--c-text', bg: '--c-ai-soft', min: 4.5, what: 'texte sur carte IA' },
  // Composants d'interface (WCAG 1.4.11) : 3:1 pour les bordures d'éléments interactifs et l'anneau de focus.
  { fg: '--c-border-strong', bg: '--c-surface', min: 3, what: 'bordure de champ' },
  { fg: '--c-border-strong', bg: '--c-bg', min: 3, what: 'bordure de champ sur fond' },
  { fg: '--c-focus', bg: '--c-surface', min: 3, what: 'anneau de focus sur surface' },
  { fg: '--c-focus', bg: '--c-bg', min: 3, what: 'anneau de focus sur fond' },
  { fg: '--c-primary', bg: '--c-surface', min: 3, what: 'bouton principal sur surface' },
  { fg: '--c-on-danger-solid', bg: '--c-danger-solid', min: 4.5, what: 'texte sur rouge plein' },
]

export function failingContrasts(tokens: Tokens): string[] {
  return CONTRAST_RULES.filter((r) => contrast(tokens[r.fg], tokens[r.bg]) < r.min).map((r) => `${r.what} (${r.fg} / ${r.bg}) : ${contrast(tokens[r.fg], tokens[r.bg]).toFixed(2)} < ${r.min}`)
}

export const isHexAccent = isHex

export function applyTokens(root: HTMLElement, tokens: Tokens) {
  for (const [k, v] of Object.entries(tokens)) root.style.setProperty(k, v)
}
