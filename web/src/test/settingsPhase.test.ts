import { flushPromises, mount } from '@vue/test-utils'
import axe from 'axe-core'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { h, ref } from 'vue'
import { resetApi } from '../api'
import { createMockApi } from '../api/mock'
import AccountsPanel from '../components/settings/AccountsPanel.vue'
import DirectoryPanel from '../components/settings/DirectoryPanel.vue'
import FollowRulesPanel from '../components/settings/FollowRulesPanel.vue'
import NotificationChannels from '../components/settings/NotificationChannels.vue'
import RecipientInput from '../components/ui/RecipientInput.vue'
import { i18n } from '../i18n'
import { useAccountsStore } from '../stores/accounts'
import { useSettingsStore } from '../stores/settings'
import { mountApp } from './helpers'

const check = async (el: Element) => (await axe.run(el, { rules: { 'landmark-main-is-top-level': { enabled: false }, 'color-contrast': { enabled: false } } })).violations.map((v) => `${v.id}: ${v.nodes[0]?.html.slice(0, 80)}`)

describe('réglages complétés : relance par compte, règles de suivi, canaux, annuaire', () => {
  it('chaque compte peut avoir son propre délai de relance (F-12, F-54), et le résume', async () => {
    const { wrapper, pinia } = await mountApp(AccountsPanel, '/reglages')
    await flushPromises()
    const accounts = useAccountsStore(pinia)
    const id = accounts.mail[0].id
    await wrapper.findAll('button').find((b) => b.text() === 'Relances')!.trigger('click')
    await flushPromises()
    const dlg = document.querySelector('[role="dialog"]')!
    expect(dlg.textContent).toContain('Relances du compte')
    await accounts.updateMail(id, { relanceHours: 72, businessDays: false })
    await flushPromises()
    expect(accounts.mail[0].relanceHours).toBe(72)
    expect(wrapper.text()).toContain('Relance : 3 jours · Heures réelles')
  })

  it('les règles de création de suivis se voient, s’ajoutent, se désactivent et se suppriment (F-66)', async () => {
    const { wrapper } = await mountApp(FollowRulesPanel, '/reglages')
    await flushPromises()
    expect(wrapper.text()).toContain('Tout mail reçu de @univ-reims.fr')
    await wrapper.find('#rule-value').setValue('sophie.laurent@univ-reims.fr')
    await wrapper.find('#rule-kind').setValue('sender')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Tout mail reçu de sophie.laurent@univ-reims.fr')
    await wrapper.find('#rule-value').setValue('pas-une-adresse')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('adresse complète')
    await wrapper.findAll('button[aria-label^="Supprimer la règle"]')[0].trigger('click')
    await flushPromises()
    expect(wrapper.text()).not.toContain('Tout mail reçu de @univ-reims.fr')
    expect(await check(wrapper.element)).toEqual([])
  })

  it('un canal par type de notification, écrit dans les réglages et lu avec ses valeurs par défaut (F-75)', async () => {
    const { wrapper, pinia } = await mountApp(NotificationChannels, '/alertes')
    const boxes = () => wrapper.findAll('input[type="checkbox"]')
    expect(boxes()).toHaveLength(12)
    const email = wrapper.find('input[aria-label="Réponse reçue : E-mail"]')
    expect((email.element as HTMLInputElement).checked).toBe(false)
    await email.setValue(true)
    expect(useSettingsStore(pinia).s.notifyChannels.replyReceived.email).toBe(true)
    expect((wrapper.find('input[aria-label="Connexion en échec : E-mail"]').element as HTMLInputElement).checked).toBe(true) // défaut
    expect(await check(wrapper.element)).toEqual([])
  })

  it('l’annuaire de l’établissement répond à la place de la liste de démonstration, avec le service (F-18)', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const api = createMockApi()
    api.directory = { status: async () => ({ configured: true }), search: async () => [{ name: 'Sophie Laurent', address: 'sophie.laurent@univ-reims.fr', detail: 'Scolarité' }] }
    resetApi(api)
    const value = ref<string[]>([])
    const w = mount(() => h(RecipientInput, { id: 'to', label: 'À', modelValue: value.value, 'onUpdate:modelValue': (v: string[]) => (value.value = v) }), { global: { plugins: [pinia, i18n] }, attachTo: document.body })
    await flushPromises()
    await w.find('#to').setValue('laur')
    await new Promise((r) => setTimeout(r, 300))
    await flushPromises()
    expect(w.find('[role="listbox"]').text()).toContain('Scolarité')
    expect(w.find('[role="listbox"]').text()).not.toContain('Marc Dubois') // pas la liste de démonstration
  })

  it('le panneau de l’annuaire dit qu’il n’est pas configuré et laisse choisir service et téléphone', async () => {
    const { wrapper, pinia } = await mountApp(DirectoryPanel, '/reglages')
    await flushPromises()
    expect(wrapper.text()).toContain('Annuaire non configuré')
    const phone = wrapper.findAll('button[role="switch"]')[1]
    await phone.trigger('click')
    expect(useSettingsStore(pinia).s.dirShowPhone).toBe(true)
    expect(await check(wrapper.element)).toEqual([])
  })
})
