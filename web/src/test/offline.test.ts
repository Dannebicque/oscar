import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { resetApi } from '../api'
import { createMockApi } from '../api/mock'
import { ApiError } from '../api/types'
import OfflineBanner from '../components/layout/OfflineBanner.vue'
import { enqueue, pending, remove, resetMemoryOutbox } from '../pwa/outbox'
import { useMailsStore } from '../stores/mails'
import ComposeView from '../views/ComposeView.vue'
import { addRecipient, mountApp } from './helpers'

beforeEach(() => resetMemoryOutbox())
afterEach(() => { Object.defineProperty(navigator, 'onLine', { value: true, configurable: true }) })

describe('hors ligne : file d’envoi (F-80, N-09)', () => {
  it('la file garde les messages dans l’ordre et les retire un à un', async () => {
    const a = await enqueue({ to: ['a@x.fr'], cc: [], bcc: [], subject: 'Un', body: '1' })
    await enqueue({ to: ['b@x.fr'], cc: [], bcc: [], subject: 'Deux', body: '2' })
    expect((await pending()).map((m) => m.payload.subject)).toEqual(['Un', 'Deux'])
    await remove(a.id)
    expect((await pending()).map((m) => m.payload.subject)).toEqual(['Deux'])
  })

  it('sans réseau, « Envoyer » met le message en file au lieu de l’abandonner ; il part au retour du réseau, une fois', async () => {
    const { wrapper, pinia } = await mountApp(ComposeView, '/ecrire')
    await flushPromises()
    const api = createMockApi()
    const sent: string[] = []
    let down = true
    api.mails.send = async (p) => { if (down) throw new ApiError(0, 'network'); sent.push(p.subject); return { followDueAt: null } }
    resetApi(api)
    await addRecipient(wrapper, 'to', 'sophie@x.fr')
    await wrapper.find('#subject').setValue('Planning')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect((await pending()).map((m) => m.payload.subject)).toEqual(['Planning'])
    expect(sent).toEqual([])
    down = false
    window.dispatchEvent(new Event('online'))
    await flushPromises()
    await new Promise((r) => setTimeout(r, 20))
    expect(sent).toEqual(['Planning'])
    expect(await pending()).toEqual([])
    void pinia; void useMailsStore
  })

  it('une réponse du serveur (refus) n’est jamais mise en file : on l’annonce comme une erreur', async () => {
    const { wrapper } = await mountApp(ComposeView, '/ecrire')
    await flushPromises()
    const api = createMockApi()
    api.mails.send = async () => { throw new ApiError(502, 'Le message n’est pas parti') }
    resetApi(api)
    await addRecipient(wrapper, 'to', 'sophie@x.fr')
    await wrapper.find('#subject').setValue('Refusé')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(await pending()).toEqual([])
  })

  it('la bannière annonce le hors-ligne et le nombre de messages en attente', async () => {
    await enqueue({ to: ['a@x.fr'], cc: [], bcc: [], subject: 'En attente', body: '' })
    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true })
    const { wrapper } = await mountApp(OfflineBanner, '/')
    window.dispatchEvent(new Event('offline'))
    await flushPromises()
    await new Promise((r) => setTimeout(r, 30))
    await flushPromises()
    expect(wrapper.text()).toContain('Hors ligne')
    expect(wrapper.text()).toContain('1 message en attente')
    expect(wrapper.attributes('role')).toBe('status')
  })
})
