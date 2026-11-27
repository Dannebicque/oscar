import { flushPromises } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import MailReader from '../components/mail/MailReader.vue'
import type { Mail } from '../domain/types'
import { useMailsStore } from '../stores/mails'
import { mountApp } from './helpers'

const msg = (id: string, name: string, folder: Mail['folder'], at: string, text: string, parents: string[] = []): Mail => ({
  id, messageId: `<${id}@x>`, parents, mailbox: 'personal', folder, from: { name, address: `${id}@x.fr` }, to: [], subject: 'Devis', paragraphs: [text], at,
  unread: false, attachments: [], suivi: null, claim: null, composing: null, notes: [], activity: [], hints: {},
})
const items = [
  msg('a', 'David', 'sent', '2026-11-25T06:10:00Z', 'Pouvez-vous m’envoyer le devis ?'),
  msg('b', 'Marc Dubois', 'inbox', '2026-11-27T08:00:00Z', 'Voici le devis.', ['<a@x>']),
]

describe('« répondu » (F-16)', () => {
  it('se déduit de ma réponse directe ou de ce que le serveur a noté, jamais sur mes propres envois', async () => {
    await mountApp({ template: '<div/>' })
    const s = useMailsStore()
    const question = msg('q', 'Claire', 'inbox', '2026-11-20T09:00:00Z', 'Dispo ?')
    const other = msg('o', 'Claire', 'inbox', '2026-11-19T09:00:00Z', 'Autre', [])
    const reply = { ...msg('r', 'David', 'sent', '2026-11-21T09:00:00Z', 'Oui', ['<o@x>', '<q@x>']), inReplyTo: '<q@x>' }
    s.items = [question, other, reply]
    expect(s.repliedAt(question)).toBe('2026-11-21T09:00:00Z')
    expect(s.repliedAt(other)).toBeNull() // citée dans le fil, mais pas répondue directement
    expect(s.repliedAt(reply)).toBeNull()
    expect(s.repliedAt({ ...other, answeredAt: '2026-11-22T09:00:00Z' })).toBe('2026-11-22T09:00:00Z')
  })

  it('un envoi en réponse le fait apparaître', async () => {
    await mountApp({ template: '<div/>' })
    const s = useMailsStore()
    await s.load()
    expect(s.repliedAt(s.byId('m5')!)).toBeNull()
    await s.send({ to: ['claire.roux@iut-troyes.fr'], cc: [], bcc: [], subject: 'Re', body: 'Oui', inReplyToMailId: 'm5' })
    expect(s.repliedAt(s.byId('m5')!)).not.toBeNull()
  })
})

describe('lire une conversation (F-16)', () => {
  async function open(id: string) {
    await mountApp({ template: '<div/>' })
    const { wrapper } = await mountApp(MailReader, '/', { mail: items.find((m) => m.id === id)! }, { keepApi: true })
    useMailsStore().items = items.map((m) => ({ ...m }))
    await flushPromises()
    return wrapper
  }

  it('le message ouvert dit qui et quand, et les précédents viennent dessous', async () => {
    const w = await open('b')
    const html = w.text()
    expect(w.find('.body .by').text()).toContain('Marc Dubois')
    expect(w.find('.body .by time').attributes('datetime')).toBe('2026-11-27T08:00:00Z')
    expect(html.indexOf('Voici le devis.')).toBeLessThan(html.indexOf('Messages précédents'))
    expect(w.find('.thread h3').text()).toBe('Messages précédents · 1 message')
    expect(w.find('.thread .line').text()).toContain('Moi')
    expect(w.find('.thread .line time').exists()).toBe(true)
  })

  it('en lisant un message ancien, on sait qu’il y a plus récent', async () => {
    const w = await open('a')
    expect(w.find('.body .by').text()).toContain('Moi')
    expect(w.text()).toContain('Marc Dubois a écrit plus récemment')
    expect(w.find('.thread').exists()).toBe(false)
  })
})
