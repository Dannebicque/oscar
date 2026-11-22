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

  /** @throws ApiError 'no-recipient' si l'adresse est introuvable ; l'erreur du serveur sinon. Rien n'est marqué envoyé en cas d'échec. */
  async function send(s: Suivi, body: string): Promise<'sent' | 'queued'> {
    const r = recipientOf(s)
    if (!r) throw new ApiError(422, 'no-recipient')
    const payload: SendPayload = { to: [r.address], cc: [], bcc: [], subject: r.subject, body, inReplyToMailId: r.mailId }
    let outcome: 'sent' | 'queued' = 'sent'
    try {
      await api.mails.send(payload)
    } catch (e) {
      // Pas de réseau (F-80) : le message patiente dans la file et part au retour de la connexion.
      if (!isOffline(e)) throw e
      await outbox.queue(payload)
      outcome = 'queued'
    }
    await suivis.relanceSent(s.id)
    return outcome
  }

  return { recipientOf, send }
}
