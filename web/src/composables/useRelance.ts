import { api, ApiError } from '../api'
import type { SendPayload } from '../api/types'
import type { Suivi } from '../domain/types'
import { useMailsStore } from '../stores/mails'
import { useSuivisStore } from '../stores/suivis'
import { isOffline, useOutbox } from './useOutbox'

/**
 * Envoyer une relance (F-63) : un vrai message, en réponse dans le même fil, parti d'un clic de l'utilisateur.
 * Le suivi existe déjà : l'envoi ne crée donc pas de nouveau suivi, il enregistre seulement la relance (compteur, prochaine date).
 */
export function useRelance() {
  const mails = useMailsStore()
  const suivis = useSuivisStore()
  const outbox = useOutbox()

  /** À qui écrire : le destinataire du message envoyé, ou l'expéditeur du message reçu. Null si le mail d'origine n'est plus là. */
  function recipientOf(s: Suivi): { address: string; mailId: string; subject: string } | null {
    const m = mails.find(s.mailId)
    if (!m) return null
    const address = m.folder === 'sent' ? m.to[0]?.address : m.from.address
    if (!address) return null
    return { address, mailId: m.id, subject: /^re\s*:/i.test(m.subject) ? m.subject : `Re : ${m.subject}` }
  }

  /**
   * Un seul message pour une ou plusieurs relances au même destinataire (F-63) : en réponse dans le fil du mail le plus récent.
   * @throws ApiError 'no-recipient' si l'adresse est introuvable ; l'erreur du serveur sinon. Rien n'est marqué envoyé en cas d'échec.
   */
  async function send(target: Suivi | Suivi[], body: string, subject?: string): Promise<'sent' | 'queued'> {
    const list = Array.isArray(target) ? target : [target]
    const r = recipientOf(list[0])
    if (!r) throw new ApiError(422, 'no-recipient')
    const payload: SendPayload = { to: [r.address], cc: [], bcc: [], subject: subject ?? r.subject, body, inReplyToMailId: r.mailId }
    let outcome: 'sent' | 'queued' = 'sent'
    try {
      await api.mails.send(payload)
    } catch (e) {
      // Pas de réseau (F-80) : le message patiente dans la file et part au retour de la connexion.
      if (!isOffline(e)) throw e
      await outbox.queue(payload)
      outcome = 'queued'
    }
    for (const x of list) await suivis.relanceSent(x.id)
    return outcome
  }

  /**
   * Regroupe les relances qui vont à la même adresse (au moins deux) ; les autres restent seules.
   * Sans adresse connue, rien n'est regroupé : on ne devine pas un destinataire.
   */
  function group(queue: Suivi[]): Suivi[][] {
    const byAddress = new Map<string, Suivi[]>()
    const out: Suivi[][] = []
    for (const s of queue) {
      const a = recipientOf(s)?.address.toLowerCase()
      if (!a) { out.push([s]); continue }
      const g = byAddress.get(a)
      if (g) g.push(s)
      else { const n = [s]; byAddress.set(a, n); out.push(n) }
    }
    return out
  }

  return { recipientOf, send, group }
}
