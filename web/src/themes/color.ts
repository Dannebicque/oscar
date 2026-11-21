/** Utilitaires de couleur : contraste WCAG et dérivation de teintes accessibles. Sans dépendance. */
export type RGB = [number, number, number]

export function hexToRgb(hex: string): RGB {
  const h = hex.replace('#', '')
  const f = h.length === 3 ? [...h].map((c) => c + c).join('') : h
  return [parseInt(f.slice(0, 2), 16), parseInt(f.slice(2, 4), 16), parseInt(f.slice(4, 6), 16)]
}
export const rgbToHex = ([r, g, b]: RGB) => '#' + [r, g, b].map((v) => Math.round(Math.max(0, Math.min(255, v))).toString(16).padStart(2, '0')).join('')
export const isHex = (v: unknown): v is string => typeof v === 'string' && /^#[0-9a-f]{6}$/i.test(v)

export function luminance(hex: string): number {
  const [r, g, b] = hexToRgb(hex).map((v) => { const c = v / 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4 })
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}
/** Rapport de contraste WCAG 2.x entre deux couleurs (1 à 21). */
export function contrast(a: string, b: string): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
  return (hi + 0.05) / (lo + 0.05)
}

/** Mélange : `amount` de b dans a (0 à 1). */
export function mix(a: string, b: string, amount: number): string {
  const [x, y] = [hexToRgb(a), hexToRgb(b)]
  return rgbToHex(x.map((v, i) => v + (y[i] - v) * amount) as RGB)
}

export function rgbToHsl([r, g, b]: RGB): [number, number, number] {
  const [R, G, B] = [r / 255, g / 255, b / 255]
  const max = Math.max(R, G, B), min = Math.min(R, G, B), l = (max + min) / 2
  if (max === min) return [0, 0, l]
  const d = max - min, s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
  const h = max === R ? (G - B) / d + (G < B ? 6 : 0) : max === G ? (B - R) / d + 2 : (R - G) / d + 4
  return [h * 60, s, l]
}
export function hslToRgb([h, s, l]: [number, number, number]): RGB {
  const k = (n: number) => (n + h / 30) % 12
  const a = s * Math.min(l, 1 - l)
  const f = (n: number) => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)))
  return [f(0) * 255, f(8) * 255, f(4) * 255]
}

/**
 * Rapproche `color` de la couleur de texte `against` jusqu'à atteindre `target` de contraste, en gardant la teinte.
 * Sert à garantir qu'un accent choisi librement reste lisible (AA = 4,5).
 */
export function ensureContrast(color: string, against: string, target: number): string {
  if (contrast(color, against) >= target) return color
  const [h, s, l] = rgbToHsl(hexToRgb(color))
  const lighter = luminance(against) < 0.5 // fond sombre : on éclaircit ; fond clair : on assombrit
  let lo = lighter ? l : 0, hi = lighter ? 1 : l
  for (let i = 0; i < 24; i++) {
    const mid = (lo + hi) / 2
    const ok = contrast(rgbToHex(hslToRgb([h, s, mid])), against) >= target
    if (lighter) ok ? (hi = mid) : (lo = mid)
    else ok ? (lo = mid) : (hi = mid)
  }
  return rgbToHex(hslToRgb([h, s, lighter ? hi : lo]))
}

/** Texte lisible sur un fond : blanc ou presque noir, selon le meilleur contraste. */
export function readableOn(bg: string): string {
  return contrast('#ffffff', bg) >= contrast('#0b1020', bg) ? '#ffffff' : '#0b1020'
}
