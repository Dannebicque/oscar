import { api } from '../api'
import { flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import MailReader from '../components/mail/MailReader.vue'
import { useMailsStore } from '../stores/mails'
import { useSettingsStore } from '../stores/settings'
import { useTasksStore } from '../stores/tasks'
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
    await wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Supprimer')!.trigger('click')
    await flushPromises()
    // Une confirmation précède la corbeille : rien ne bouge tant qu'on ne confirme pas.
    expect(s.byId('m1')?.folder).toBe('inbox')
    expect(document.body.textContent).toContain('Mettre ce message à la corbeille ?')
    ;[...document.body.querySelectorAll('button')].find((b) => b.textContent?.includes('Mettre à la corbeille'))!.click()
    await flushPromises()
    expect(s.byId('m1')?.folder).toBe('trash')
  })

  it('« Ne plus demander » coupe la confirmation ; le message part alors tout de suite', async () => {
    const first = await mountApp({ template: '<div/>' })
    const store = useMailsStore(first.pinia)
    await store.load()
    const { wrapper } = await mountApp(MailReader, '/mails/m1', { mail: { ...store.byId('m1')! } })
    await flushPromises()
    const s = useMailsStore()
    await s.load()
    await wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Supprimer')!.trigger('click')
    await flushPromises()
    ;(document.body.querySelector('[role="checkbox"]') as HTMLElement).click()
    ;[...document.body.querySelectorAll('button')].find((b) => b.textContent?.includes('Mettre à la corbeille'))!.click()
    await flushPromises()
    expect(useSettingsStore().s.confirmDelete).toBe(false)
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
    const reply = vi.spyOn(api.mails, 'replyInvitation')
    await card.findAll('button').find((b) => b.text() === 'Accepter')!.trigger('click')
    await flushPromises()
    // La fenêtre montre le message : rien n'est parti ni inscrit.
    expect(reply).not.toHaveBeenCalled()
    const message = document.body.querySelector<HTMLTextAreaElement>('#inv-message')!
    expect(message.value).toContain('Je confirme ma présence à « Soutenance de stage : M. Lemoine »')
    message.value = 'Bonjour, je viendrai.'
    message.dispatchEvent(new Event('input'))
    await flushPromises()
    ;[...document.body.querySelectorAll('button')].find((b) => b.textContent?.trim() === 'Envoyer la réponse')!.click()
    await flushPromises()
    expect(reply).toHaveBeenCalledWith('m9', expect.objectContaining({ response: 'accepted', message: 'Bonjour, je viendrai.', send: true }))
    expect(s.byId('m9')!.invitationResponse).toBe('accepted')
    expect(useAgendaStore(pinia).events.length).toBe(before + 1)
    await wrapper.setProps({ mail: s.byId('m9')! })
    expect(wrapper.find('.invite').text()).toContain('Tu as accepté cette invitation')
    // Répondu : plus de bouton pour refaire la même chose ; changer d'avis passe par « Modifier ma réponse ».
    const buttons = () => wrapper.find('.invite').findAll('button').map((b) => b.text())
    expect(buttons()).toEqual(['Modifier ma réponse'])
    await wrapper.find('.invite').findAll('button')[0].trigger('click')
    expect(buttons()).toEqual(['Garder ma réponse', 'Peut-être', 'Refuser'])
  })
})

describe('suggestion de tâche pendant la rédaction (F-73)', () => {
  it('propose une tâche, ne crée rien sans clic, et ne la crée qu’à l’envoi si elle est cochée', async () => {
    const { addRecipient } = await import('./helpers')
    const { wrapper, pinia } = await mountApp(ComposeView, '/ecrire')
    await flushPromises()
    await addRecipient(wrapper, 'to', 'sophie.laurent@univ-reims.fr')
    await wrapper.find('#subject').setValue('Planning')
    await wrapper.find('#body').setValue('Bonjour Sophie, peux-tu confirmer le nombre de participants avant mardi ?')
    await new Promise((r) => setTimeout(r, 1100))
    await flushPromises()
    const card = wrapper.find('.taskhint')
    expect(card.exists()).toBe(true)
    expect(card.text()).toContain('Suggestion')
    const tasks = useTasksStore(pinia)
    await tasks.load()
    const before = tasks.items.length
    await wrapper.find('form').trigger('submit') // envoi sans cocher : aucune tâche
    await flushPromises()
    expect(tasks.items.length).toBe(before)
  })
})
