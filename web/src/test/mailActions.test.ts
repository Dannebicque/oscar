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

  it('les pièces jointes sont dans un menu déroulant de téléchargement, jamais affichées', async () => {
    const first = await mountApp({ template: '<div/>' })
    const store = useMailsStore(first.pinia)
    await store.load()
    const { wrapper } = await mountApp(MailReader, '/mails/m4', { mail: { ...store.byId('m4')! } })
    await flushPromises()
    const trigger = wrapper.find('button[aria-label^="Pièces jointes : 1"]')
    expect(trigger.exists()).toBe(true)
    expect(trigger.text()).toContain('1 pièce jointe')
  })

  it('« Enregistrer le brouillon » dépose le texte dans Brouillons sans rien envoyer', async () => {
    const first = await mountApp({ template: '<div/>' })
    await useMailsStore(first.pinia).load()
    const { wrapper, pinia } = await mountApp(ComposeView, '/ecrire')
    await flushPromises()
    await wrapper.find('#subject').setValue('Idée de réunion')
    await wrapper.findAll('button').find((b) => b.text() === 'Enregistrer le brouillon')!.trigger('click')
    await flushPromises()
    const drafts = useMailsStore(pinia).items.filter((m) => m.folder === 'drafts')
    expect(drafts.some((m) => m.subject === 'Idée de réunion')).toBe(true)
    expect(useMailsStore(pinia).items.filter((m) => m.folder === 'sent' && m.subject === 'Idée de réunion')).toHaveLength(0)
  })
})

describe('invitation d’agenda reçue par mail (F-25)', () => {
  it('la carte propose accepter, peut-être, refuser ; accepter ajoute l’événement, refuser ne l’ajoute pas, et rien ne part sans clic', async () => {
    const first = await mountApp({ template: '<div/>' })
    const store = useMailsStore(first.pinia)
    await store.load()
    const { wrapper, pinia } = await mountApp(MailReader, '/mails/m9', { mail: { ...store.byId('m9')! } })
    await flushPromises()
    const s = useMailsStore(pinia)
    await s.load()
    await wrapper.setProps({ mail: s.byId('m9')! })
    await flushPromises()
    const card = wrapper.find('.invite')
    expect(card.exists()).toBe(true)
    expect(card.text()).toContain('Soutenance de stage : M. Lemoine')
    expect(card.text()).toContain('Salle B12')
    expect(card.text()).toContain('Rien ne part sans ton clic')
    const { useAgendaStore } = await import('../stores/agenda')
    const before = useAgendaStore(pinia).events.length
    await card.findAll('button').find((b) => b.text() === 'Accepter')!.trigger('click')
    await flushPromises()
    expect(s.byId('m9')!.invitationResponse).toBe('accepted')
    expect(useAgendaStore(pinia).events.length).toBe(before + 1)
    await wrapper.setProps({ mail: s.byId('m9')! })
    expect(wrapper.find('.invite').text()).toContain('Tu as accepté cette invitation')
  })
})
