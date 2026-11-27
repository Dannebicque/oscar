/** Un Message-ID s'écrit avec ou sans chevrons selon qui l'a noté (envoi, synchro) : on compare toujours normalisé. */
export const bareId = (id: string) => id.trim().replace(/^<|>$/g, '').toLowerCase()
export const sameId = (a: string | null | undefined, b: string | null | undefined) => !!a && !!b && bareId(a) === bareId(b)
/** Objet sans son préfixe de réponse ou de transfert. */
export const plainSubject = (subject: string) => subject.replace(/^(re|tr)\s*:\s*/i, '')
