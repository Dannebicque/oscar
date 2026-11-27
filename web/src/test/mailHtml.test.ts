import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api'
import MailBodyText from '../components/mail/MailBodyText.vue'
import { MAIL_SANDBOX, mailDocument } from '../domain/mailFrame'
import type { Mail } from '../domain/types'
import { useMailsStore } from '../stores/mails'
import { mountApp } from './helpers'

const mail = (patch: Partial<Mail> = {}): Mail => ({
  id: 'h', mailbox: 'personal', folder: 'inbox', from: { name: 'Sophie', address: 's@x.fr' }, to: [], subject: 'Lettre', paragraphs: ['Bonjour en texte'], at: '2026-11-20T09:00:00Z',
  unread: false, attachments: [], suivi: null, claim: null, composing: null, notes: [], activity: [], hints: {}, ...patch,
})

describe('mail mis en forme (F-16)', () => {
  afterEach(() => vi.restoreAllMocks())

  it('le cadre ne laisse ni script ni chargement distant sans demande', () => {
    expect(MAIL_SANDBOX).not.toContain('allow-scripts')
    expect(MAIL_SANDBOX).not.toContain('allow-forms')
    expect(mailDocument('<p>x</p>', false)).toContain("default-src 'none'; style-src 'unsafe-inline'; img-src data:;")
    expect(mailDocument('<p>x</p>', true)).toContain('img-src data: https:;')
  })

  it('montre la mise en forme dans le cadre, avec « Texte seul » à la demande', async () => {
    const { wrapper: w } = await mountApp(MailBodyText, '/', { mail: mail({ html: '<p>Bonjour <b>gras</b></p>' }) })
    const frame = w.get('iframe')
    expect(frame.attributes('sandbox')).toBe(MAIL_SANDBOX)
    expect(frame.attributes('srcdoc')).toContain('<b>gras</b>')
    expect(frame.attributes('title')).toContain('Lettre')
    expect(w.text()).not.toContain('Bonjour en texte')
    const toggle = w.findAll('button').find((b) => b.text() === 'Texte seul')!
    expect(toggle.attributes('aria-pressed')).toBe('false')
    await toggle.trigger('click')
    expect(w.find('iframe').exists()).toBe(false)
    expect(w.text()).toContain('Bonjour en texte')
  })

  it('un message en texte n’a ni cadre ni bascule', async () => {
    const { wrapper: w } = await mountApp(MailBodyText, '/', { mail: mail() })
    expect(w.find('iframe').exists()).toBe(false)
    expect(w.find('button').exists()).toBe(false)
    expect(w.text()).toContain('Bonjour en texte')
  })

  it('les images distantes ne reviennent qu’au clic, et la liste rechargée ne les oublie pas', async () => {
    await mountApp({ template: '<div/>' })
    const mails = useMailsStore()
    vi.spyOn(api.mails, 'list').mockImplementation(async () => [mail({ paragraphs: [] })])
    const body = vi.spyOn(api.mails, 'body').mockImplementation(async (_id, images) => ({ paragraphs: [], html: images ? '<img src="https://x.fr/a.png">' : '<p>[Logo]</p>', blockedImages: images ? 0 : 1 }))
    await mails.load()
    await mails.loadBody('h')
    await mails.loadBody('h')
    expect(body).toHaveBeenCalledTimes(1)
    expect(mails.byId('h')).toMatchObject({ blockedImages: 1, remoteImages: false })
    await mails.showImages('h')
    await flushPromises()
    await mails.load()
    expect(body).toHaveBeenLastCalledWith('h', true)
    expect(mails.byId('h')).toMatchObject({ html: '<img src="https://x.fr/a.png">', blockedImages: 0, remoteImages: true })
  })
})
