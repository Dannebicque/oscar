import type { CustomFolder, FolderInfo, MoveTarget } from './types'

export const isCustom = (f: string): f is CustomFolder => f.startsWith('f:')
export const customOf = (path: string): CustomFolder => `f:${path}`
export const pathOf = (f: CustomFolder) => f.slice(2)

/** Un dossier personnel dans l'arborescence : son niveau d'imbrication et son nom court. */
export interface FolderRow extends FolderInfo { depth: number; target: CustomFolder }

/** Arborescence à plat, parents avant enfants, triée par nom ; les dossiers de base n'y figurent pas. */
export function folderTree(list: FolderInfo[]): FolderRow[] {
  return list
    .filter((f) => !f.system)
    .map((f) => ({ ...f, depth: f.path.split(f.delimiter).length - 1, target: customOf(f.path) }))
    .sort((a, b) => a.path.localeCompare(b.path, undefined, { sensitivity: 'base' }))
}

/** Libellé lisible d'une destination : le nom du dossier de base (traduit par l'appelant) ou le chemin « Projets › Jury ». */
export const prettyPath = (path: string, delimiter = '/') => path.split(delimiter).join(' › ')
export type { MoveTarget }
