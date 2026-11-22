import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, api } from '../api'
import AdminAudit from '../components/admin/AdminAudit.vue'
import AdminHealth from '../components/admin/AdminHealth.vue'
import AdminUsers from '../components/admin/AdminUsers.vue'
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

describe('administration : comptes, santé, journal', () => {
  const click = (label: string) => ([...document.querySelectorAll('[role="dialog"] button')].find((b) => b.textContent?.includes(label)) as HTMLElement).click()

  it('liste les comptes avec leurs états, sans aucun contenu', async () => {
    const { wrapper } = await mountApp(AdminUsers, '/administration')
    await flushPromises()
    const text = wrapper.text()
    expect(text).toContain('Claire Roux')
    expect(text).toContain('Désactivé')
    expect(text).toContain('Administrateur')
    expect(text).not.toMatch(/Planning des soutenances|Devis des affiches/)
    expect(text).toContain('Jamais ce qu’il contient')
  })

  it('désactiver et réactiver, rendre administrateur, inviter', async () => {
    const { wrapper, pinia } = await mountApp(AdminUsers, '/administration')
    await flushPromises()
    const disable = vi.spyOn(api.instance, 'setDisabled')
    await wrapper.findAll('.item').find((i) => i.text().includes('Claire'))!.findAll('button').find((b) => b.text() === 'Désactiver')!.trigger('click')
    await flushPromises()
    expect(disable).toHaveBeenCalledWith('u2', true)
    expect(wrapper.findAll('.item').find((i) => i.text().includes('Claire'))!.text()).toContain('Désactivé')

    const invite = vi.spyOn(api.instance, 'invite')
    await wrapper.findAll('button').find((b) => b.text().includes('Inviter'))!.trigger('click')
    await flushPromises()
    const set = (id: string, v: string) => { const el = document.body.querySelector(`#${id}`) as HTMLInputElement; el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })) }
    set('inv-name', 'Nouvelle Personne'); set('inv-email', 'nouvelle@exemple.fr')
    ;(document.body.querySelector('#invite-form') as HTMLFormElement).requestSubmit()
    await flushPromises()
    expect(invite).toHaveBeenCalledWith('Nouvelle Personne', 'nouvelle@exemple.fr')
    expect(wrapper.text()).toContain('Nouvelle Personne')
    void pinia
  })

  it('supprimer demande de recopier l’adresse et ne supprime rien avant', async () => {
    const { wrapper } = await mountApp(AdminUsers, '/administration')
    await flushPromises()
    const del = vi.spyOn(api.instance, 'deleteUser')
    await wrapper.findAll('.item').find((i) => i.text().includes('Marc'))!.findAll('button').find((b) => b.text().includes('Supprimer'))!.trigger('click')
    await flushPromises()
    const input = document.body.querySelector('#del-confirm') as HTMLInputElement
    input.value = 'mauvaise@adresse.fr'
    input.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    click('Supprimer le compte')
    await flushPromises()
    expect(document.body.textContent).toContain('Recopie l’adresse du compte pour confirmer')
    expect(wrapper.text()).toContain('Marc Dubois')
    input.value = 'marc@iut-troyes.fr'
    input.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    click('Supprimer le compte')
    await flushPromises()
    expect(del).toHaveBeenLastCalledWith('u3', 'marc@iut-troyes.fr')
    expect(wrapper.text()).not.toContain('Marc Dubois')
  })

  it('la santé dit les problèmes en clair, avec icône et texte, et le journal liste les actions', async () => {
    const health = (await mountApp(AdminHealth, '/administration')).wrapper
    await flushPromises()
    expect(health.text()).toContain('Base de données')
    expect(health.text()).toContain('ne se synchronisent plus')
    expect(health.text()).toContain('À surveiller')
    expect(health.text()).toContain('OK')
    const audit = (await mountApp(AdminAudit, '/administration')).wrapper
    await flushPromises()
    expect(audit.text()).toContain('Réglages modifiés')
    expect(audit.text()).toContain('nom')
    expect(audit.text()).toContain('Compte désactivé')
    expect(audit.text()).toContain('marc@iut-troyes.fr')
  })
})
