import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetApi } from '../api'
import { createHttpMailApi, createHttpSuivisApi } from '../api/http'
import { createMockApi } from '../api/mock'
import { useMailsStore } from '../stores/mails'
import { useSuivisStore } from '../stores/suivis'
import ComposeView from '../views/ComposeView.vue'
import { useUiStore } from '../stores/ui'
import { mountApp } from './helpers'

const RAW_REPLY = { id: 'u2', folder: 'INBOX', messageId: '<reponse-1@x>', inReplyTo: '<devis-1@x>', references: ['<devis-1@x>'], fromName: 'Marc', fromAddress: 'marc@x.fr', toAddresses: [], subject: 'Re: Re: Devis', date: '2026-11-20T11:00:00+00:00' }
const RAW_MAIL = { id: 'u1', folder: 'INBOX', messageId: '<devis-1@x>', fromName: '', fromAddress: 'marc@x.fr', toAddresses: ['david@x.fr'], subject: 'Re: Devis', date: '2026-11-20T10:00:00+00:00' }
const RAW_SUIVI = { id: 's1', subject: 'Devis', contact: 'Marc', direction: 'waiting', status: 'waiting', dueAt: '2026-11-25', relanceCount: 0, noRelance: false, mailbox: 'personal', replyReceived: true, suggestedByAi: false, mailId: '<devis-1@x>' }

function stubFetch(routes: Record<string, unknown>) {
  vi.stubGlobal('fetch', vi.fn(async (url: string) => {
    const hit = Object.entries(routes).find(([k]) => String(url).endsWith(k))
    return hit ? { ok: true, status: 200, json: async () => hit[1] } : { ok: false, status: 404, json: async () => ({}) }
  }))
}
afterEach(() => { vi.unstubAllGlobals(); document.body.innerHTML = '' })

describe('client HTTP des mails', () => {
  it('convertit les en-têtes de l’API vers le modèle du front', async () => {
    stubFetch({ '/api/mails': [RAW_MAIL] })
    const [m] = await createHttpMailApi('http://api').list()
    expect(m).toMatchObject({ id: 'u1', messageId: '<devis-1@x>', folder: 'inbox', from: { name: 'marc@x.fr', address: 'marc@x.fr' }, mailbox: 'personal', paragraphs: [] })
  })
  it('lit le corps à la demande', async () => {
    stubFetch({ '/api/mails/u1/body': { paragraphs: ['Bonjour', 'Merci'] } })
    expect(await createHttpMailApi('http://api').body('u1')).toEqual(['Bonjour', 'Merci'])
  })
  it('refuse d’envoyer au lieu de faire semblant tant que SMTP n’est pas branché', async () => {
    await expect(createHttpMailApi('http://api').send({ to: 'a', subject: 'b', body: 'c', follow: true })).rejects.toThrow('unsupported:send')
  })
  it('complète les champs absents des suivis (journal, responsable)', async () => {
    stubFetch({ '/api/suivis': [RAW_SUIVI] })
    const [s] = await createHttpSuivisApi('http://api').list()
    expect(s).toMatchObject({ journal: [], responsible: null, relanceAt: null, replyReceived: true })
  })
})

describe('mails sur l’API', () => {
  it('rattache un mail à son suivi par Message-ID, et une réponse par le fil', async () => {
    stubFetch({ '/api/mails': [RAW_MAIL, RAW_REPLY], '/api/suivis': [RAW_SUIVI], '/api/mails/u1/body': { paragraphs: ['Voici le devis.'] } })
    const { pinia } = await mountApp({ template: '<div/>' })
    resetApi({ ...createMockApi(), mails: createHttpMailApi('http://api'), suivis: createHttpSuivisApi('http://api') })
    const suivis = useSuivisStore(pinia)
    const mails = useMailsStore(pinia)
    await suivis.load()
    await mails.load()
    expect(mails.byId('u1')?.suivi).toBe('s1')
    expect(mails.byId('u2')?.suivi).toBe('s1')
    expect(mails.find('<devis-1@x>')?.id).toBe('u1')
    await mails.loadBody('u1')
    expect(mails.byId('u1')?.paragraphs).toEqual(['Voici le devis.'])
  })
})

describe('Écrire sans SMTP', () => {
  it('annonce l’échec, ne crée aucun suivi et reste sur la page', async () => {
    const { wrapper, router } = await mountApp(ComposeView, '/ecrire')
    resetApi({ ...createMockApi(), mails: createHttpMailApi('http://api') })
    await wrapper.find('#to').setValue('Marc <marc@x.fr>')
    await wrapper.find('#subject').setValue('Test')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    const toast = useUiStore().toasts.at(-1)
    expect(toast?.tone).toBe('error')
    expect(toast?.message).toContain('pas encore branché')
    expect(useSuivisStore().items).toHaveLength(0)
    expect(router.currentRoute.value.path).toBe('/ecrire')
  })
})
