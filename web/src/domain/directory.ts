/**
 * Annuaire des destinataires (F-18). Aujourd'hui une liste de démonstration : le LDAP de l'établissement
 * (nom, prénom, e-mail au minimum) viendra derrière la même fonction.
 */
export interface DirectoryEntry {
  name: string
  address: string
}

export const DIRECTORY: DirectoryEntry[] = [
  { name: 'Sophie Laurent', address: 'sophie.laurent@univ-reims.fr' },
  { name: 'Marc Dubois', address: 'marc.dubois@imprimerie.fr' },
  { name: 'Claire Petit', address: 'claire.petit@entreprise.fr' },
  { name: 'Claire Roux', address: 'claire.roux@iut-troyes.fr' },
  { name: 'Léa Martin', address: 'lea.martin@iut-troyes.fr' },
  { name: 'Scolarité', address: 'scolarite@iut-troyes.fr' },
]

const fold = (s: string) => s.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()

/** Recherche sans tenir compte des accents ni de la casse, sur le nom et l'adresse. */
export function searchDirectory(query: string, limit = 6): DirectoryEntry[] {
  const q = fold(query.trim())
  if (!q) return []
  return DIRECTORY.filter((e) => fold(`${e.name} ${e.address}`).includes(q)).slice(0, limit)
}
