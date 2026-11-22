import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api'
import IdentitiesPanel from '../components/settings/IdentitiesPanel.vue'
import { useAccountsStore } from '../stores/accounts'
import ComposeView from '../views/ComposeView.vue'
import { addRecipient, mountApp } from './helpers'

afterEach(() => { vi.restoreAllMocks(); document.body.innerHTML = '' })

const click = (label: string) => [...document.body.querySelectorAll('button')].find((b) => b.textContent?.includes(label))!.click()

describe('identités et signatures (F-19)', () => {
  it('liste les identités avec leurs repères, et l’identité principale ne se supprime pas', async () => {
    const { wrapper, pinia } = await mountApp(IdentitiesPanel, '/reglages')
    await useAccountsStore(pinia).load()
    await flushPromises()
    const items = wrapper.findAll('.item')
    expect(items).toHaveLength(3) // deux identités personnelles + l'identité d'équipe de la boîte partagée
    expect(items[0].text()).toContain('Par défaut')
    expect(items[0].text()).toContain('Adresse du compte')
    expect(items[0].text()).not.toContain('Supprimer')
    expect(items[1].text()).toContain('etudes@iut-troyes.fr')
    expect(items[1].text()).toContain('Réponses vers secretariat@iut-troyes.fr')
    expect(items[1].text()).toContain('Supprimer')
  })

  it('ajoute une identité, la met par défaut, puis la supprime : la principale redevient celle par défaut', async () => {
    const { wrapper, pinia } = await mountApp(IdentitiesPanel, '/reglages')
    const accounts = useAccountsStore(pinia)
    await accounts.load()
    await flushPromises()
    click('Ajouter une identité')
    await flushPromises()
    const set = (id: string, v: string) => { const el = document.body.querySelector(`#${id}`) as HTMLInputElement; el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })) }
    set('id-name', 'Jury de janvier'); set('id-address', 'jury@iut-troyes.fr'); set('id-sig', '-- \nLe jury')
    await flushPromises()
    expect(document.body.textContent).toContain('Aperçu')
    ;(document.body.querySelector('#identity-form') as HTMLFormElement).requestSubmit()
    await flushPromises()
    const created = accounts.identities.find((i) => i.address === 'jury@iut-troyes.fr')!
    expect(created).toMatchObject({ name: 'Jury de janvier', primary: false, isDefault: false })
    await accounts.makeDefaultIdentity(created.id)
    expect(accounts.defaultIdentity().id).toBe(created.id)
    await accounts.removeIdentity(created.id)
    expect(accounts.defaultIdentity().primary).toBe(true)
    await expect(accounts.removeIdentity('id1')).rejects.toThrow('principale')
    void wrapper
  })

  it('le menu « De » apparaît avec plusieurs identités ; changer d’identité change la signature, et l’envoi la transmet', async () => {
    const { wrapper, pinia } = await mountApp(ComposeView, '/ecrire')
    await useAccountsStore(pinia).load()
    await flushPromises()
    const select = wrapper.find('#from')
    expect(select.exists()).toBe(true)
    const body = () => (wrapper.find('#body').element as HTMLTextAreaElement).value
    expect(body()).toContain('Département informatique')
    await select.setValue('id2')
    await flushPromises()
    expect(body()).toContain('La direction des études')
    expect(body()).not.toContain('Département informatique')

    const send = vi.spyOn(api.mails, 'send')
    await addRecipient(wrapper, 'to', 'Marc <marc@x.fr>')
    await wrapper.find('#subject').setValue('Bonjour')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(send.mock.calls[0][0]).toMatchObject({ identityId: 'id2', accountId: 'acc1' })
  })

  it('répondre à un mail adressé à un alias choisit cette identité ; une signature retouchée à la main n’est pas écrasée', async () => {
    const { wrapper } = await mountApp(ComposeView, '/ecrire?reply=m6')
    await flushPromises()
    const select = wrapper.find('#from')
    expect((select.element as HTMLSelectElement).value).toBe('id2')
    expect((wrapper.find('#body').element as HTMLTextAreaElement).value).toContain('La direction des études')
    await select.setValue('id1')
    await flushPromises()
    const ta = wrapper.find('#body')
    await ta.setValue('Bonjour\n\n-- \nSignature à moi')
    await select.setValue('id2')
    await flushPromises()
    expect((ta.element as HTMLTextAreaElement).value).toContain('Signature à moi')
    expect((ta.element as HTMLTextAreaElement).value).not.toContain('La direction des études')
  })
})

describe('signature en HTML', () => {
  it('bascule texte / HTML, aperçu assaini dans un cadre isolé, et enregistrement avec le texte dérivé', async () => {
    const { wrapper, pinia } = await mountApp(IdentitiesPanel, '/reglages')
    const accounts = useAccountsStore(pinia)
    await accounts.load()
    await flushPromises()
    click('Ajouter une identité')
    await flushPromises()
    const set = (id: string, v: string) => { const el = document.body.querySelector(`#${id}`) as HTMLInputElement; el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })) }
    set('id-name', 'Direction'); set('id-address', 'direction@iut-troyes.fr')
    const radios = [...document.body.querySelectorAll('[role="radio"], button')].filter((b) => b.textContent?.trim() === 'HTML')
    ;(radios[0] as HTMLElement).click()
    await flushPromises()
    set('id-sig-html', '<p style="color:red">Marc <b>D.</b></p><script>alert(1)</script><a href="javascript:x()">pi</a>')
    await new Promise((r) => setTimeout(r, 450))
    await flushPromises()
    const frame = document.body.querySelector('iframe') as HTMLIFrameElement
    expect(frame).not.toBeNull()
    expect(frame.getAttribute('sandbox')).toBe('')
    expect(frame.getAttribute('srcdoc')).toContain('<b>D.</b>')
    expect(frame.getAttribute('srcdoc')).not.toContain('script')
    expect(frame.getAttribute('srcdoc')).not.toContain('javascript')
    expect(document.body.textContent).toContain('Version texte')
    ;(document.body.querySelector('#identity-form') as HTMLFormElement).requestSubmit()
    await flushPromises()
    const made = accounts.identities.find((i) => i.address === 'direction@iut-troyes.fr')!
    expect(made.signatureHtml).toContain('<b>D.</b>')
    expect(made.signatureHtml).not.toContain('script')
    expect(made.signature).toContain('Marc D.')
    void wrapper
  })
})

describe('identité d’une boîte partagée', () => {
  it('répondre à un mail de contact@ choisit « De : contact@ » avec la signature de l’équipe', async () => {
    const { wrapper } = await mountApp(ComposeView, '/ecrire?reply=c1')
    await flushPromises()
    const select = wrapper.find('#from')
    expect((select.element as HTMLSelectElement).value).toBe('shared:sm1')
    expect(select.text()).toContain('Équipe Contact IUT <contact@iut-troyes.fr> · boîte partagée')
    expect((wrapper.find('#body').element as HTMLTextAreaElement).value).toContain('L’équipe contact')
    const send = vi.spyOn(api.mails, 'send')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    await vi.waitFor(() => expect(send).toHaveBeenCalled())
    expect(send.mock.calls[0][0]).toMatchObject({ identityId: 'shared:sm1', accountId: 'acc1' })
  })

  it('un mail personnel reste sur l’identité personnelle ; l’identité d’équipe reste choisissable', async () => {
    const { wrapper } = await mountApp(ComposeView, '/ecrire?reply=m1')
    await flushPromises()
    expect((wrapper.find('#from').element as HTMLSelectElement).value).toBe('id1')
    expect(wrapper.findAll('#from option').map((o) => o.attributes('value'))).toContain('shared:sm1')
  })

  it('un administrateur règle le nom et la signature de l’équipe', async () => {
    const { pinia } = await mountApp(IdentitiesPanel, '/reglages')
    const accounts = useAccountsStore(pinia)
    await accounts.load()
    await flushPromises()
    await accounts.updateSharedSender('sm1', { senderName: 'Accueil IUT', signature: '-- \nAccueil' })
    expect(accounts.senders.find((i) => i.shared)).toMatchObject({ name: 'Accueil IUT', address: 'contact@iut-troyes.fr' })
    await accounts.updateSharedSender('sm1', { senderName: null, signature: null })
    expect(accounts.senders.find((i) => i.shared)!.name).toBe('Contact')
  })
})
