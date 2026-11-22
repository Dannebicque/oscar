import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api'
import RelanceBatch from '../components/suivis/RelanceBatch.vue'
import { useSuivisStore } from '../stores/suivis'
import { useMailsStore } from '../stores/mails'
import { mountApp } from './helpers'

afterEach(() => { vi.restoreAllMocks(); document.body.innerHTML = '' })

async function open() {
  const ctx = await mountApp(RelanceBatch, '/suivis', { open: false })
  const suivis = useSuivisStore(ctx.pinia)
  await Promise.all([suivis.load(), useMailsStore(ctx.pinia).load()])
  await ctx.wrapper.setProps({ open: true })
  await flushPromises()
  return { ...ctx, suivis }
}
const buttons = () => [...document.body.querySelectorAll('button')]
const click = (label: string) => buttons().find((b) => b.textContent?.includes(label))!.click()

describe('relances en lot (F-63)', () => {
  it('liste les relances dues avec un brouillon à relire et rappelle le plafond', async () => {
    await open()
    expect(document.body.textContent).toContain('Relances à relire')
    expect(document.body.textContent).toContain('plafond du jour : 5')
    expect((document.body.querySelector('textarea') as HTMLTextAreaElement).value).toContain('Marc')
  })

  it('rien ne part tant que la relance n’est pas cochée relue, puis l’envoi demande confirmation et part en réponse dans le fil', async () => {
    const { suivis } = await open()
    const send = vi.spyOn(api.mails, 'send')
    const before = suivis.byId('s2')!.relanceCount
    const sendReady = buttons().find((b) => b.textContent?.includes('Envoyer la relance relue') || b.textContent?.includes('Aucune relance prête'))!
    expect(sendReady.hasAttribute('disabled')).toBe(true)
    ;(document.body.querySelector('.rd input') as HTMLInputElement).click()
    await flushPromises()
    click('Envoyer la relance relue')
    await flushPromises()
    expect(send).not.toHaveBeenCalled()
    expect(document.body.textContent).toContain('Envoyer cette relance à')
    click('Oui, envoyer')
    await flushPromises()
    expect(send).toHaveBeenCalledTimes(1)
    expect(send.mock.calls[0][0]).toMatchObject({ to: ['marc.dubois@imprimerie.fr'], inReplyToMailId: 'm2', subject: 'Re : Devis des affiches' })
    expect(send.mock.calls[0][0].followHours).toBeUndefined()
    expect(suivis.byId('s2')!.relanceCount).toBe(before + 1)
    expect(document.body.textContent).toContain('Relance envoyée')
  })

  it('un envoi refusé ne marque rien comme envoyé et garde la relance', async () => {
    const { suivis } = await open()
    vi.spyOn(api.mails, 'send').mockRejectedValue(new Error('smtp'))
    const before = suivis.byId('s2')!.relanceCount
    const sendBtn = buttons().find((b) => b.getAttribute('aria-label') === null && b.textContent?.trim() === 'Envoyer')!
    sendBtn.click()
    await flushPromises()
    expect(suivis.byId('s2')!.relanceCount).toBe(before)
    expect(document.body.textContent).toContain('n’est pas partie')
    expect(document.body.querySelector('textarea')).not.toBeNull()
  })

  it('reporter ou ne plus relancer sort la relance de la liste sans rien envoyer', async () => {
    const { suivis } = await open()
    const send = vi.spyOn(api.mails, 'send')
    click('Ne plus relancer')
    await flushPromises()
    expect(suivis.byId('s2')!.noRelance).toBe(true)
    expect(document.body.textContent).toContain('Ne sera plus relancée')
    expect(send).not.toHaveBeenCalled()
  })
})
