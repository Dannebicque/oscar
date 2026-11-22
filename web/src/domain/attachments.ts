/** Pièces jointes à la rédaction : mêmes règles que l'API (somme limitée par compte, 10 fichiers au plus). */
export const MAX_FILES = 10
export const DEFAULT_LIMIT_MB = 10
const MB = 1024 * 1024

export const limitBytes = (limitMb: number | null | undefined) => (limitMb ?? DEFAULT_LIMIT_MB) * MB

export function sizeLabel(n: number): string {
  if (n < 1024) return `${n} o`
  if (n < MB) return `${Math.max(1, Math.round(n / 1024))} Ko`
  return `${(n / MB).toFixed(n < 10 * MB ? 1 : 0).replace('.0', '')} Mo`
}

export type Refusal = { name: string; reason: 'size' | 'count' | 'empty' }

/** Ajoute des fichiers à une liste : doublons ignorés, les autres refusés avec leur raison. Rien n'est lu ni envoyé ici. */
export function addFiles(current: File[], incoming: File[], limit: number): { files: File[]; added: File[]; refused: Refusal[] } {
  const files = [...current]
  const added: File[] = []
  const refused: Refusal[] = []
  let total = files.reduce((n, f) => n + f.size, 0)
  for (const f of incoming) {
    if (files.some((x) => x.name === f.name && x.size === f.size && x.lastModified === f.lastModified)) continue
    if (f.size === 0) { refused.push({ name: f.name, reason: 'empty' }); continue }
    if (files.length >= MAX_FILES) { refused.push({ name: f.name, reason: 'count' }); continue }
    if (total + f.size > limit) { refused.push({ name: f.name, reason: 'size' }); continue }
    files.push(f)
    added.push(f)
    total += f.size
  }
  return { files, added, refused }
}
