/**
 * Réglages usuels des fournisseurs de messagerie : l'assistant de connexion préremplit les serveurs d'après le domaine de l'adresse.
 * Un domaine inconnu reçoit une proposition « imap.domaine » / « smtp.domaine », à vérifier par le test de connexion.
 */
export type Encryption = 'none' | 'ssl' | 'tls'
export interface MailServers { imapHost: string; imapPort: number; imapEncryption: Encryption; smtpHost: string; smtpPort: number; smtpEncryption: Encryption }
export interface Preset extends MailServers { id: string; appPassword: boolean }

const PRESETS: { match: RegExp; preset: Preset }[] = [
  { match: /^(gmail|googlemail)\.com$/, preset: { id: 'gmail', imapHost: 'imap.gmail.com', imapPort: 993, imapEncryption: 'ssl', smtpHost: 'smtp.gmail.com', smtpPort: 587, smtpEncryption: 'tls', appPassword: true } },
  { match: /^(outlook|hotmail|live|msn)\.[a-z.]+$/, preset: { id: 'outlook', imapHost: 'outlook.office365.com', imapPort: 993, imapEncryption: 'ssl', smtpHost: 'smtp-mail.outlook.com', smtpPort: 587, smtpEncryption: 'tls', appPassword: true } },
  { match: /^yahoo\.[a-z.]+$/, preset: { id: 'yahoo', imapHost: 'imap.mail.yahoo.com', imapPort: 993, imapEncryption: 'ssl', smtpHost: 'smtp.mail.yahoo.com', smtpPort: 587, smtpEncryption: 'tls', appPassword: true } },
  { match: /^(icloud|me|mac)\.com$/, preset: { id: 'icloud', imapHost: 'imap.mail.me.com', imapPort: 993, imapEncryption: 'ssl', smtpHost: 'smtp.mail.me.com', smtpPort: 587, smtpEncryption: 'tls', appPassword: true } },
  { match: /^(orange|wanadoo)\.fr$/, preset: { id: 'orange', imapHost: 'imap.orange.fr', imapPort: 993, imapEncryption: 'ssl', smtpHost: 'smtp.orange.fr', smtpPort: 465, smtpEncryption: 'ssl', appPassword: false } },
  { match: /^(free|laposte)\.(fr|net)$/, preset: { id: 'free', imapHost: 'imap.free.fr', imapPort: 993, imapEncryption: 'ssl', smtpHost: 'smtp.free.fr', smtpPort: 465, smtpEncryption: 'ssl', appPassword: false } },
]

export const domainOf = (address: string) => address.split('@')[1]?.trim().toLowerCase() ?? ''
export const isAddress = (v: string) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim())

/** Serveurs proposés pour une adresse ; `known` dit si le fournisseur est reconnu. */
export function guessServers(address: string): { servers: MailServers; known: boolean; appPassword: boolean; provider: string } {
  const d = domainOf(address)
  const hit = PRESETS.find((p) => p.match.test(d))?.preset
  if (hit) return { servers: hit, known: true, appPassword: hit.appPassword, provider: hit.id }
  return {
    servers: { imapHost: d ? `imap.${d}` : '', imapPort: 993, imapEncryption: 'ssl', smtpHost: d ? `smtp.${d}` : '', smtpPort: 587, smtpEncryption: 'tls' },
    known: false, appPassword: false, provider: 'other',
  }
}
