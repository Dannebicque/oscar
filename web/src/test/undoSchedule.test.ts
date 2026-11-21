import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useMailsStore } from '../stores/mails'
import { useOutgoingStore } from '../stores/outgoing'
import { useSuivisStore } from '../stores/suivis'
import ComposeView from '../views/ComposeView.vue'
import { addRecipient, mountApp } from './helpers'

afterEach(() => { vi.useRealTimers(); document.body.innerHTML = '' })
beforeEach(() => vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval', 'setTimeout', 'clearTimeout'] }))

async function write(subject: string, undo: 0 | 5 | 10 = 10) {
  const ctx = await mountApp(ComposeView, '/ecrire', {}, { undoSeconds: undo })
  await addRecipient(ctx.wrapper, 'to', 'Marc <marc@x.fr>')
  await ctx.wrapper.find('#subject').setValue(subject)
  return ctx
}

describe('Annuler l’envoi (F-73)', () => {
  it('le message attend le délai : rien n’est parti ni créé tant qu’il attend, puis il part', async () => {
    const { wrapper, router, pinia } = await write('Devis')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    const out = useOutgoingStore(pinia)
    expect(out.pending).toBe(true)
    expect(router.currentRoute.value.name).toBe('mails')
    expect(useSuivisStore(pinia).items.find((s) => s.subject === 'Devis')).toBeUndefined()
    vi.advanceTimersByTime(9000)
    await flushPromises()
    expect(useSuivisStore(pinia).items.find((s) => s.subject === 'Devis')).toBeUndefined()
    vi.advanceTimersByTime(1500)
    await flushPromises()
    expect(out.pending).toBe(false)
    expect(useSuivisStore(pinia).items.find((s) => s.subject === 'Devis')).toBeDefined()
  })

  it('annuler rend le message à l’éditeur, rien n’est parti', async () => {
    const { wrapper, router, pinia } = await write('Devis annulé')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    const out = useOutgoingStore(pinia)
    const back = out.cancel()
    expect(back?.subject).toBe('Devis annulé')
    vi.advanceTimersByTime(20000)
    await flushPromises()
    expect(useSuivisStore(pinia).items.find((s) => s.subject === 'Devis annulé')).toBeUndefined()
    expect(out.restore?.subject).toBe('Devis annulé')
    expect(router.currentRoute.value.name).toBe('mails')
  })

  it('« Envoyer maintenant » saute l’attente', async () => {
    const { wrapper, pinia } = await write('Urgent')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    await useOutgoingStore(pinia).sendNow()
    await flushPromises()
    expect(useSuivisStore(pinia).items.find((s) => s.subject === 'Urgent')).toBeDefined()
  })

  it('délai à 0 : le message part tout de suite', async () => {
    const { wrapper, pinia } = await write('Direct', 0)
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(useOutgoingStore(pinia).pending).toBe(false)
    expect(useSuivisStore(pinia).items.find((s) => s.subject === 'Direct')).toBeDefined()
  })
})

describe('Mails programmés (F-73)', () => {
  it('programmer ne crée aucun suivi tant que le message n’est pas parti ; annuler le rend', async () => {
    const { wrapper, router, pinia } = await write('Pour demain', 0)
    const open = wrapper.findAll('button').find((b) => b.text().includes('Programmer'))
    await open!.trigger('click')
    await flushPromises()
    const confirm = [...document.body.querySelectorAll('button')].find((b) => b.textContent?.includes('Programmer l’envoi'))
    confirm!.click()
    await flushPromises()
    const mails = useMailsStore(pinia)
    expect(mails.scheduled).toHaveLength(1)
    expect(mails.scheduled[0].subject).toBe('Pour demain')
    expect(useSuivisStore(pinia).items.find((s) => s.subject === 'Pour demain')).toBeUndefined()
    expect(router.currentRoute.value.name).toBe('mails')
    const back = await mails.cancelScheduled(mails.scheduled[0].id)
    expect(back.subject).toBe('Pour demain')
    expect(mails.scheduled).toHaveLength(0)
  })
})
