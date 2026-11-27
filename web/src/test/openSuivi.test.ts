import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { api, ApiError } from '../api'
import { useMailsStore } from '../stores/mails'
import { useSuivisStore } from '../stores/suivis'
import type { Mail, Suivi } from '../domain/types'
import SuivisView from '../views/SuivisView.vue'
import { mountApp } from './helpers'

afterEach(() => { document.body.innerHTML = '' })

const mail = (id: string, messageId: string, folder: Mail['folder'], at: string, parents: string[] = []): Mail => ({
  id, messageId, parents, mailbox: 'personal', folder, from: { name: 'Marc', address: 'm@x.fr' }, to: [], subject: 'Devis', paragraphs: [], at, unread: false,
  attachments: [], suivi: null, claim: null, composing: null, notes: [], activity: [], hints: {},
})

describe('« Ouvrir » un suivi mène à son message (F-59, F-70)', () => {
  it('reconnaît le Message-ID avec ou sans chevrons, et montre le dernier message du fil', async () => {
    await mountApp({ template: '<div/>' })
    const mails = useMailsStore()
    // Comme avec la vraie API : l'envoi note « <id> », la synchro note « id ».
    mails.items = [mail('a', 'devis-1@x.fr', 'sent', '2026-11-20T09:00:00Z'), mail('b', 'rep-1@x.fr', 'inbox', '2026-11-21T09:00:00Z', ['<devis-1@x.fr>'])]
    expect(mails.find('<DEVIS-1@x.fr>')?.id).toBe('a')
    expect(mails.openTarget('<devis-1@x.fr>')?.id).toBe('b')
    expect(mails.openTarget('<inconnu@x.fr>')).toBeUndefined()
  })

  it('sans message, ouvre la fiche du suivi au lieu d’une liste sans rapport', async () => {
    const { wrapper, router, pinia } = await mountApp(SuivisView, '/suivis')
    const suivis = useSuivisStore(pinia)
    const first = suivis.items.find((s: Suivi) => s.status !== 'done' && s.mailbox === 'personal')!
    suivis.items = suivis.items.map((s) => ({ ...s, mailId: null }))
    await flushPromises()
    const row = wrapper.findAll('li').find((li) => li.text().includes(first.subject) && li.findAll('button').some((b) => b.text().includes('Ouvrir')))!
    await row.findAll('button').find((b) => b.text().includes('Ouvrir'))!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/suivis')
    const dlg = document.body.querySelector('[role="dialog"]')!
    expect(dlg.textContent).toContain(first.subject)
    expect(dlg.textContent).toContain('n’est relié à aucun message')
  })
})

describe('recharger la liste ne vide pas le message ouvert (F-16)', () => {
  it('garde le texte déjà lu', async () => {
    await mountApp({ template: '<div/>' })
    const mails = useMailsStore()
    const header = mail('a', 'x@x.fr', 'inbox', '2026-11-20T09:00:00Z')
    vi.spyOn(api.mails, 'list').mockImplementation(async () => [{ ...header }])
    vi.spyOn(api.mails, 'body').mockResolvedValue({ paragraphs: ['Bonjour David,'], html: null, blockedImages: 0 })
    await mails.load()
    await mails.loadBody('a')
    await mails.load()
    expect(mails.byId('a')?.paragraphs).toEqual(['Bonjour David,'])
    vi.restoreAllMocks()
  })
})

describe('message disparu du serveur (F-16)', () => {
  it('est dit « plus là », pas « sans texte » ni « en panne »', async () => {
    await mountApp({ template: '<div/>' })
    const mails = useMailsStore()
    mails.items = [mail('a', 'x@x.fr', 'inbox', '2026-11-20T09:00:00Z')]
    vi.spyOn(api.mails, 'body').mockRejectedValue(new ApiError(410, 'plus là'))
    await mails.loadBody('a')
    expect([mails.gone.has('a'), mails.bodyError.has('a'), mails.loadingBody.has('a')]).toEqual([true, false, false])
    vi.restoreAllMocks()
  })
})
