/** Horloge injectable : les tests figent l'heure, l'app utilise l'heure réelle. */
export const clock = { now: (): Date => new Date() }
