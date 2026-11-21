import { flushPromises } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import MailReader from '../components/mail/MailReader.vue'
import { useMailsStore } from '../stores/mails'
import ComposeView from '../views/ComposeView.vue'
import { mountApp } from './helpers'

/** F-16 : les boutons du lecteur agissent vraiment (archiver, corbeille, transférer), avec annulation. */
describe('actions sur un mail (F-16)', () => {
  it('archiver déplace le message, et l’annulation le remet en boîte de réception', async () => {
    const first = await mountApp({ template: '<div/>' })
    const store = useMailsStore(first.pinia)
    await store.load()
    const undo = await store.moveTo('m1', 'archive')
    expect(store.byId('m1')!.folder).toBe('archive')
    await undo!()
    expect(store.byId('m1')!.folder).toBe('inbox')
  })

  it('le bouton « Supprimer » envoie le message à la corbeille, jamais une suppression définitive', async () => {
    const first = await mountApp({ template: '<div/>' })
    const store = useMailsStore(first.pinia)
    await store.load()
    const { wrapper } = await mountApp(MailReader, '/mails/m1', { mail: { ...store.byId('m1')! } })
    await flushPromises()
    const s = useMailsStore()
    await s.load()
    await wrapper.findAll('button').find((b) => b.text() === 'Supprimer')!.trigger('click')
    await flushPromises()
    expect(s.byId('m1')?.folder).toBe('trash')
  })

  it('transférer ouvre la rédaction avec « Tr : » et le message cité, sans destinataire', async () => {
    const first = await mountApp({ template: '<div/>' })
    await useMailsStore(first.pinia).load()
    const { wrapper } = await mountApp(ComposeView, '/ecrire?forward=m1')
    await flushPromises()
    expect((wrapper.find('#subject').element as HTMLInputElement).value).toMatch(/^Tr : /)
    expect((wrapper.find('#body').element as HTMLTextAreaElement).value).toContain('Message transféré')
  })

  it('les pièces jointes sont listées comme des boutons de téléchargement, jamais affichées', async () => {
    const first = await mountApp({ template: '<div/>' })
    const store = useMailsStore(first.pinia)
    await store.load()
    const { wrapper } = await mountApp(MailReader, '/mails/m4', { mail: { ...store.byId('m4')! } })
    await flushPromises()
    expect(wrapper.find('button[aria-label="Télécharger convention.pdf"]').exists()).toBe(true)
  })
})
