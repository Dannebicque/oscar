import { flushPromises } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import MailReader from '../components/mail/MailReader.vue'
import { useMailsStore } from '../stores/mails'
import ComposeView from '../views/ComposeView.vue'
import { mountApp } from './helpers'

describe('Oscar résume et propose une réponse (F-11, F-64, F-74)', () => {
  it('le lecteur affiche le résumé d’Oscar avec la pastille « Suggestion »', async () => {
    const { pinia } = await mountApp({ template: '<div/>' })
    const store = useMailsStore(pinia)
    await store.load()
    const { wrapper } = await mountApp(MailReader, '/mails/m5', { mail: { ...store.items[0] } })
    await flushPromises()
    const mail = store.items.find((m) => m.id === 'm5')!
    await wrapper.setProps({ mail })
    await flushPromises()
    expect(wrapper.find('.summary').exists()).toBe(true)
    expect(wrapper.find('.summary').text()).toContain('disponibilités')
    expect(wrapper.find('.summary').text()).toContain('Suggestion')
    expect(wrapper.text()).toContain('Réponse suggérée')
  })

  it('la rédaction prépare un brouillon avec les créneaux choisis, sans rien envoyer', async () => {
    const first = await mountApp({ template: '<div/>' })
    await useMailsStore(first.pinia).load()
    const start = useMailsStore(first.pinia).items.find((m) => m.id === 'm5')!.hints.availability!.slots[0].start
    const { wrapper } = await mountApp(ComposeView, `/ecrire?reply=m5&ai=1&slots=${encodeURIComponent(start)}`)
    await flushPromises()
    await flushPromises()
    const body = (wrapper.find('#body').element as HTMLTextAreaElement).value
    expect(body).toContain('Bonjour Claire')
    expect(body).toContain('- ') // le créneau est listé
    expect(wrapper.text()).toContain('Suggestion')
    expect(wrapper.text()).toContain('rien ne part sans ton clic')
  })

  it('choisir « Court » fait suivre le brouillon, qui reste modifiable à la main', async () => {
    const first = await mountApp({ template: '<div/>' })
    await useMailsStore(first.pinia).load()
    const { wrapper } = await mountApp(ComposeView, '/ecrire?reply=m5&ai=1')
    await flushPromises()
    await flushPromises()
    const warm = (wrapper.find('#body').element as HTMLTextAreaElement).value
    expect(warm).toContain('Cordialement')
    await wrapper.findAll('button').find((b) => b.text() === 'Court')!.trigger('click')
    await flushPromises()
    await flushPromises()
    expect((wrapper.find('#body').element as HTMLTextAreaElement).value).not.toContain('Cordialement')
  })
})
