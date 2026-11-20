import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { resetApi } from '../api'
import { createHttpApi, extractAddress } from '../api/http'
import { createMockApi } from '../api/mock'
import { authEvents, saveToken } from '../api/session'
import { ApiError } from '../api/types'
import { useMailsStore } from '../stores/mails'
import { useSuivisStore } from '../stores/suivis'
import { useUiStore } from '../stores/ui'
import ComposeView from '../views/ComposeView.vue'
import { mountApp } from './helpers'

const RAW_MAIL = { id: 'u1', folder: 'INBOX', mailbox: 'personal', messageId: '<devis-1@x>', fromName: '', fromAddress: 'marc@x.fr', toAddresses: ['david@x.fr'], subject: 'Re: Devis', date: '2026-11-20T10:00:00+00:00' }
const RAW_REPLY = { id: 'u2', folder: 'INBOX', messageId: '<reponse-1@x>', inReplyTo: '<devis-1@x>', references: ['<devis-1@x>'], fromName: 'Marc', fromAddress: 'marc@x.fr', toAddresses: [], subject: 'Re: Re: Devis', date: '2026-11-20T11:00:00+00:00' }
const RAW_SUIVI = { id: 's1', subject: 'Devis', contact: 'Marc', direction: 'waiting', status: 'waiting', dueAt: '2026-11-25', relanceCount: 0, noRelance: false, mailbox: 'personal', replyReceived: true, suggestedByAi: false, mailId: '<devis-1@x>' }

type Handler = (url: string, init: RequestInit) => { status?: number; body?: unknown } | undefined
const err = (status: number, body: unknown) => ({ __status: status, body })
let calls: { method: string; url: string; headers: Record<string, string>; body?: unknown }[] = []

function stubFetch(routes: Record<string, unknown | Handler>) {
  calls = []
  vi.stubGlobal('fetch', vi.fn(async (url: string, init: RequestInit = {}) => {
    calls.push({ method: init.method ?? 'GET', url, headers: (init.headers ?? {}) as Record<string, string>, body: init.body ? JSON.parse(String(init.body)) : undefined })
    const key = `${init.method ?? 'GET'} ${String(url).replace(/^https?:\/\/[^/]+/, '')}`
    if (!(key in routes)) return { ok: false, status: 404, json: async () => ({}) }
    const v = routes[key] as { __status?: number; body?: unknown } | Handler
    const r = typeof v === 'function' ? v(url, init) : v && typeof v === 'object' && '__status' in v ? { status: v.__status, body: v.body } : { body: v }
    const status = r?.status ?? 200
    return { ok: status < 400, status, json: async () => r?.body ?? {} }
  }))
}
beforeEach(() => saveToken('jeton-test'))
afterEach(() => { vi.unstubAllGlobals(); saveToken(null); document.body.innerHTML = '' })

describe('client HTTP', () => {
  it('envoie le jeton porteur et convertit les mails vers le modèle du front', async () => {
    stubFetch({ 'GET /api/mails': [RAW_MAIL] })
    const [m] = await createHttpApi('http://api').mails.list()
    expect(calls[0].headers.Authorization).toBe('Bearer jeton-test')
    expect(m).toMatchObject({ id: 'u1', messageId: '<devis-1@x>', folder: 'inbox', from: { name: 'marc@x.fr' }, mailbox: 'personal', paragraphs: [] })
  })
  it('une réponse 401 renvoie à la connexion', async () => {
    stubFetch({ 'GET /api/suivis': err(401, { title: 'expiré' }) })
    const onUnauthorized = vi.spyOn(authEvents, 'onUnauthorized')
    await expect(createHttpApi('http://api').suivis.list()).rejects.toBeInstanceOf(ApiError)
    expect(onUnauthorized).toHaveBeenCalled()
  })
  it('lit le corps et les suggestions à la demande', async () => {
    stubFetch({ 'GET /api/mails/u1/body': { paragraphs: ['Bonjour'] }, 'GET /api/mails/u1/hints': { task: { title: 'x', dueAt: '2026-11-24', quote: 'avant mardi' }, suggestFollow: true } })
    const api = createHttpApi('http://api')
    expect(await api.mails.body('u1')).toEqual(['Bonjour'])
    expect((await api.mails.hints('u1')).task?.quote).toBe('avant mardi')
  })
  it('complète les champs que l’API omet quand ils sont vides', async () => {
    stubFetch({ 'GET /api/suivis': [RAW_SUIVI] })
    const [s] = await createHttpApi('http://api').suivis.list()
    expect(s).toMatchObject({ journal: [], responsible: null, relanceAt: null, replyReceived: true, takenByName: null, notes: [] })
  })
  it('PATCH en merge-patch, pas en JSON simple', async () => {
    stubFetch({ 'PATCH /api/suivis/s1': { ...RAW_SUIVI, noRelance: true } })
    await createHttpApi('http://api').suivis.update('s1', { noRelance: true })
    expect(calls[0].headers['Content-Type']).toBe('application/merge-patch+json')
  })
  it('l’envoi cible le premier compte et renvoie la date de relance créée par le serveur', async () => {
    stubFetch({ 'GET /api/mail-accounts': [{ id: 'acc1' }], 'POST /api/mails/send': { messageId: '<x>', suivi: { dueAt: '2026-11-22' } } })
    const r = await createHttpApi('http://api').mails.send({ to: ['a@x.fr'], cc: [], bcc: [], subject: 'S', body: 'B', followHours: 48 })
    expect(r.followDueAt).toBe('2026-11-22')
    expect(calls.at(-1)?.body).toMatchObject({ accountId: 'acc1', followHours: 48 })
  })
  it('sans compte de messagerie, l’envoi échoue clairement', async () => {
    stubFetch({ 'GET /api/mail-accounts': [] })
    await expect(createHttpApi('http://api').mails.send({ to: ['a@x.fr'], cc: [], bcc: [], subject: 'S', body: 'B' })).rejects.toMatchObject({ message: 'no-account' })
  })
  it('un conflit de prise en charge garde le message du serveur', async () => {
    stubFetch({ 'POST /api/suivis/s1/claim': err(409, { title: 'David s’en occupe.' }) })
    await expect(createHttpApi('http://api').mails.claim('m', 's1', 'take')).rejects.toMatchObject({ status: 409, message: 'David s’en occupe.' })
  })
  it('le brouillon de relance est null quand l’IA est coupée (le front garde son modèle)', async () => {
    stubFetch({ 'POST /api/suivis/s1/relance-draft': err(403, { title: 'désactivé' }) })
    expect(await createHttpApi('http://api').suivis.draftRelance('s1')).toBeNull()
  })
  it('extrait une adresse de « Nom <adresse> » et refuse le reste', () => {
    expect(extractAddress('Sophie Laurent <Sophie@X.fr>')).toBe('sophie@x.fr')
    expect(extractAddress('marc@x.fr')).toBe('marc@x.fr')
    expect(extractAddress('pas une adresse')).toBeNull()
    expect(extractAddress('a@b')).toBe('a@b')
  })
})

describe('mails sur l’API', () => {
  it('rattache un mail à son suivi par Message-ID, une réponse par le fil, et recopie l’état d’équipe', async () => {
    const team = { ...RAW_SUIVI, id: 's9', mailbox: 'contact', mailId: '<alias@x>', status: 'inProgress', takenByName: 'Claire', notes: [{ id: 'n', author: 'Marc', text: 'ok', at: '2026-11-20T10:00:00Z' }], journal: [{ at: '2026-11-20T09:00:00Z', author: 'Claire', action: 'take' }] }
    stubFetch({
      'GET /api/mails': [RAW_MAIL, RAW_REPLY, { ...RAW_MAIL, id: 'u3', messageId: '<alias@x>', mailbox: 'contact' }],
      'GET /api/suivis': [RAW_SUIVI, team], 'GET /api/mails/u1/body': { paragraphs: ['Voici le devis.'] },
    })
    const { pinia } = await mountApp({ template: '<div/>' })
    resetApi(createHttpApi('http://api'))
    const suivis = useSuivisStore(pinia)
    const mails = useMailsStore(pinia)
    await suivis.load()
    await mails.load()
    expect(mails.byId('u1')?.suivi).toBe('s1')
    expect(mails.byId('u2')?.suivi).toBe('s1')
    expect(mails.find('<devis-1@x>')?.id).toBe('u1')
    const shared = mails.byId('u3')!
    expect(shared.claim).toEqual({ state: 'taken', by: 'Claire' })
    expect(shared.notes[0].text).toBe('ok')
    expect(shared.activity[0].action).toBe('take')
    await mails.loadBody('u1')
    expect(mails.byId('u1')?.paragraphs).toEqual(['Voici le devis.'])
  })

  it('après un conflit de prise en charge, l’écran montre quand même l’état réel', async () => {
    stubFetch({
      'GET /api/mails': [{ ...RAW_MAIL, id: 'u3', messageId: '<alias@x>', mailbox: 'contact' }],
      'GET /api/suivis': [{ ...RAW_SUIVI, id: 's9', mailbox: 'contact', mailId: '<alias@x>', status: 'inProgress', takenByName: 'Claire' }],
      'POST /api/suivis/s9/claim': err(409, { title: 'Claire s’en occupe.' }),
    })
    const { pinia } = await mountApp({ template: '<div/>' })
    resetApi(createHttpApi('http://api'))
    const mails = useMailsStore(pinia)
    await useSuivisStore(pinia).load()
    await mails.load()
    await expect(mails.take('u3')).rejects.toMatchObject({ status: 409 })
    expect(mails.byId('u3')?.claim?.by).toBe('Claire')
  })
})

describe('Écrire sur l’API', () => {
  it('un envoi refusé annonce l’échec, ne crée aucun suivi et garde le texte', async () => {
    const { wrapper, router } = await mountApp(ComposeView, '/ecrire')
    stubFetch({ 'GET /api/mail-accounts': [{ id: 'acc1' }], 'POST /api/mails/send': err(502, { title: 'serveur d’envoi' }) })
    resetApi(createHttpApi('http://api'))
    await wrapper.find('#to').setValue('Marc <marc@x.fr>')
    await wrapper.find('#subject').setValue('Test')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    const toast = useUiStore().toasts.at(-1)
    expect(toast?.tone).toBe('error')
    expect(toast?.message).toContain('Rien n’a été créé')
    expect((wrapper.find('#subject').element as HTMLInputElement).value).toBe('Test')
    expect(router.currentRoute.value.path).toBe('/ecrire')
    expect(calls.some((c) => c.url.endsWith('/api/suivis'))).toBe(false)
  })
  it('refuse une adresse invalide sans appeler le serveur', async () => {
    const { wrapper } = await mountApp(ComposeView, '/ecrire')
    stubFetch({})
    resetApi(createHttpApi('http://api'))
    await wrapper.find('#to').setValue('pas une adresse')
    await wrapper.find('#subject').setValue('Test')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').text()).toContain('destinataire')
    expect(calls).toHaveLength(0)
  })
  it('un envoi réussi redirige et annonce la date de relance', async () => {
    const { wrapper, router } = await mountApp(ComposeView, '/ecrire')
    stubFetch({ 'GET /api/mail-accounts': [{ id: 'acc1' }], 'POST /api/mails/send': { messageId: '<x>', suivi: { dueAt: '2026-11-22' } }, 'GET /api/suivis': [], 'GET /api/mails': [] })
    resetApi(createHttpApi('http://api'))
    await wrapper.find('#to').setValue('marc@x.fr, claire@x.fr')
    await wrapper.find('#subject').setValue('Test')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(calls.find((c) => c.url.endsWith('/api/mails/send'))?.body).toMatchObject({ to: ['marc@x.fr', 'claire@x.fr'], followHours: 48, followSuggestedByAi: true })
    expect(router.currentRoute.value.name).toBe('mails')
    expect(useUiStore().toasts.at(-1)?.message).toContain('Je te préviens')
  })
})

void createMockApi
