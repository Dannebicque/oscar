import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, api } from '../api'
import { useInstanceStore } from '../stores/instance'
import AdminView from '../views/AdminView.vue'
import SetupView from '../views/SetupView.vue'
import { mountApp } from './helpers'

afterEach(() => { vi.restoreAllMocks(); document.body.innerHTML = '' })

const type = (wrapper: { find: (s: string) => { setValue: (v: string) => Promise<void> } }, id: string, v: string) => wrapper.find(`#${id}`).setValue(v)

describe('assistant de premier lancement', () => {
  it('trois étapes validées une à une, puis création de l’administrateur et passage à l’administration', async () => {
    const { wrapper, router } = await mountApp(SetupView, '/installation')
    const setup = vi.spyOn(api.instance, 'setup').mockResolvedValue('jeton-admin')
    expect(wrapper.text()).toContain('code d’installation')
    expect(wrapper.text()).toContain('docker compose')

    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('Saisis le code')
    await type(wrapper, 'code', 'abc123')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('Ce compte administre l’instance')

    await type(wrapper, 'name', 'Ada'); await type(wrapper, 'email', 'pas-une-adresse'); await type(wrapper, 'password', 'court'); await type(wrapper, 'again', 'court')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('n’a pas l’air valide')
    await type(wrapper, 'email', 'ada@exemple.fr'); await type(wrapper, 'password', 'mot-de-passe-long-1'); await type(wrapper, 'again', 'autre-mot-de-passe-2')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('ne sont pas les mêmes')
    await type(wrapper, 'again', 'mot-de-passe-long-1')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.text()).toContain('Trois réglages de base')

    await type(wrapper, 'iname', 'Cabinet Martin')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(setup).toHaveBeenCalledWith({ code: 'abc123', name: 'Ada', email: 'ada@exemple.fr', password: 'mot-de-passe-long-1', config: { instanceName: 'Cabinet Martin', registration: 'closed', supportEmail: '' } })
    expect(router.currentRoute.value.name).toBe('admin')
  })

  it('un mauvais code ramène à la première étape avec le message du serveur', async () => {
    const { wrapper } = await mountApp(SetupView, '/installation')
    vi.spyOn(api.instance, 'setup').mockRejectedValue(new ApiError(403, 'Ce code d’installation n’est pas le bon.', { field: 'code' }))
    await type(wrapper, 'code', 'faux')
    await wrapper.find('form').trigger('submit')
    await type(wrapper, 'name', 'Ada'); await type(wrapper, 'email', 'ada@exemple.fr'); await type(wrapper, 'password', 'mot-de-passe-long-1'); await type(wrapper, 'again', 'mot-de-passe-long-1')
    await wrapper.find('form').trigger('submit')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Ce code d’installation n’est pas le bon.')
    expect(wrapper.text()).toContain('Code d’installation')
  })
})

describe('administration de l’instance', () => {
  it('affiche la promesse de confidentialité et enregistre les réglages globaux', async () => {
    const { wrapper, pinia } = await mountApp(AdminView, '/administration')
    await flushPromises()
    expect(wrapper.text()).toContain('aucun mail, aucun agenda, aucune tâche d’un utilisateur n’est lisible ici')
    const update = vi.spyOn(api.instance, 'updateConfig')
    await type(wrapper, 'a-name', 'Cabinet Martin'); await type(wrapper, 'a-mb', '20')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(update).toHaveBeenCalledWith(expect.objectContaining({ instanceName: 'Cabinet Martin', defaultAttachmentMb: 20, aiExternalAllowed: true }))
    expect(useInstanceStore(pinia).name).toBe('Oscar') // le mode démo ne renvoie pas le nouveau nom : seul l'appel compte ici
  })

  it('montre les erreurs de validation du serveur sous chaque champ', async () => {
    const { wrapper } = await mountApp(AdminView, '/administration')
    await flushPromises()
    vi.spyOn(api.instance, 'updateConfig').mockRejectedValue(new ApiError(422, 'Certains réglages ne sont pas valides.', { fields: { legalUrl: 'Adresse en https:// attendue, ou vide.', defaultAttachmentMb: 'Nombre entier de 1 à 25.' } }))
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Adresse en https:// attendue')
    expect(wrapper.text()).toContain('Nombre entier de 1 à 25.')
  })
})
