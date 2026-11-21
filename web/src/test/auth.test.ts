import { flushPromises } from '@vue/test-utils'
import axe from 'axe-core'
import { describe, expect, it } from 'vitest'
import { guessServers, isAddress } from '../domain/mailPresets'
import ForgotView from '../views/ForgotView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import ResetView from '../views/ResetView.vue'
import WelcomeView from '../views/WelcomeView.vue'
import { mountApp } from './helpers'

const check = async (el: Element) => (await axe.run(el, { rules: { 'landmark-main-is-top-level': { enabled: false }, 'landmark-no-duplicate-main': { enabled: false }, 'color-contrast': { enabled: false } } })).violations.map((v) => `${v.id}: ${v.nodes[0]?.html.slice(0, 80)}`)

describe('pages de connexion et assistant (F-10, N-05)', () => {
  it('les serveurs sont devinés d’après le domaine, et un domaine inconnu reçoit une proposition à vérifier', () => {
    expect(guessServers('a@gmail.com')).toMatchObject({ known: true, appPassword: true, servers: { imapHost: 'imap.gmail.com', imapPort: 993, smtpPort: 587 } })
    expect(guessServers('a@iut-troyes.fr')).toMatchObject({ known: false, servers: { imapHost: 'imap.iut-troyes.fr', smtpHost: 'smtp.iut-troyes.fr' } })
    expect(isAddress('david@iut-troyes.fr')).toBe(true)
    expect(isAddress('david@')).toBe(false)
  })

  it('la connexion propose le mot de passe oublié, la création de compte et le CAS, sans erreur d’accessibilité', async () => {
    const { wrapper } = await mountApp(LoginView, '/connexion')
    await flushPromises()
    expect(wrapper.text()).toContain('Mot de passe oublié ?')
    expect(wrapper.text()).toContain('Créer un compte')
    expect(wrapper.text()).toContain('établissement')
    expect(await check(wrapper.element)).toEqual([])
  })

  it('le champ mot de passe se montre et se masque, avec un état annoncé', async () => {
    const { wrapper } = await mountApp(LoginView, '/connexion')
    await flushPromises()
    const input = wrapper.find('#password')
    expect(input.attributes('type')).toBe('password')
    const eye = wrapper.find('button[aria-pressed]')
    await eye.trigger('click')
    expect(wrapper.find('#password').attributes('type')).toBe('text')
    expect(eye.attributes('aria-pressed')).toBe('true')
  })

  it('l’inscription refuse un mot de passe trop court avant tout appel et indique la force', async () => {
    const { wrapper } = await mountApp(RegisterView, '/inscription')
    await wrapper.find('#name').setValue('David')
    await wrapper.find('#email').setValue('d@iut-troyes.fr')
    await wrapper.find('#password').setValue('court')
    expect(wrapper.text()).toContain('Faible')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('au moins 10 caractères')
    expect(await check(wrapper.element)).toEqual([])
  })

  it('mot de passe oublié : la réponse confirme sans dire si le compte existe', async () => {
    const { wrapper } = await mountApp(ForgotView, '/mot-de-passe-oublie')
    await wrapper.find('#email').setValue('quelqu-un@x.fr')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Si un compte existe')
    expect(await check(wrapper.element)).toEqual([])
  })

  it('réinitialisation : sans jeton dans le lien, on propose d’en demander un nouveau', async () => {
    const { wrapper } = await mountApp(ResetView, '/mot-de-passe/reinitialiser')
    await flushPromises()
    expect(wrapper.text()).toContain('incomplet')
  })

  it('l’assistant : adresse reconnue, test des deux serveurs, puis connexion seulement si le test réussit', async () => {
    const { wrapper } = await mountApp(WelcomeView, '/bienvenue')
    await wrapper.find('#w-address').setValue('prof@gmail.com')
    expect(wrapper.text()).toContain('Gmail reconnu')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('h1').text()).toContain('identifiants')
    await wrapper.find('#w-pass').setValue('mot-de-passe-application')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).not.toContain('Connecter ma messagerie') // pas avant le test
    await wrapper.findAll('button').find((b) => b.text() === 'Tester la connexion')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Réception : connexion réussie')
    expect(wrapper.text()).toContain('Connecter ma messagerie')
    expect(await check(wrapper.element)).toEqual([])
  })

  it('l’assistant : un test en échec n’autorise pas la connexion et dit quel serveur pose problème', async () => {
    const { wrapper } = await mountApp(WelcomeView, '/bienvenue')
    await wrapper.find('#w-address').setValue('prof@fail.fr')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    await wrapper.find('#w-pass').setValue('x'.repeat(12))
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    await wrapper.findAll('button').find((b) => b.text() === 'Tester la connexion')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Réception : connexion impossible')
    expect(wrapper.text()).not.toContain('Connecter ma messagerie')
  })
})

describe('mon compte (N-05, N-06)', () => {
  it('le panneau propose changement de mot de passe, export et suppression, avec confirmation avant d’effacer', async () => {
    const { session } = await import('../api')
    session.hasPassword = true
    const { default: Panel } = await import('../components/settings/MyAccountPanel.vue')
    const { wrapper } = await mountApp(Panel, '/reglages')
    await flushPromises()
    expect(wrapper.text()).toContain('Changer de mot de passe')
    expect(wrapper.text()).toContain('Exporter mes données')
    await wrapper.findAll('button').find((b) => b.text() === 'Supprimer mon compte')!.trigger('click')
    await flushPromises()
    const dlg = document.querySelector('[role="dialog"]')!
    expect(dlg.textContent).toContain('Supprimer ton compte ?')
    expect(dlg.textContent).toContain('définitivement')
    expect(await check(wrapper.element)).toEqual([])
  })
})
