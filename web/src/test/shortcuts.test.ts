import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia, setActivePinia } from 'pinia'
import ShortcutsDialog from '../components/layout/ShortcutsDialog.vue'
import { useShortcuts } from '../composables/useShortcuts'
import { i18n } from '../i18n'
import { useSettingsStore } from '../stores/settings'
import { useUiStore } from '../stores/ui'
import MailView from '../views/MailView.vue'
import { mountApp } from './helpers'

// Les réglages sont gardés en local : sans remise à zéro, « raccourcis désactivés » fuyait dans les tests suivants.
afterEach(() => { document.body.innerHTML = ''; localStorage.clear() })
// Déclaré après : s'exécute avant. Démonter d'abord, sinon un composant encore vivant se met à jour dans un document vidé (erreur non gérée, CI en échec).
enableAutoUnmount(afterEach)

async function setup() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:rest(.*)*', component: { template: '<div/>' } }] })
  router.push('/')
  await router.isReady()
  const Host = defineComponent({ setup() { useShortcuts(); return () => h('div', [h('input', { id: 'field' }), h('button', 'ok')]) } })
  const w = mount(Host, { global: { plugins: [pinia, router, i18n] }, attachTo: document.body })
  return { w, router, ui: useUiStore(pinia), settings: useSettingsStore(pinia) }
}
const press = (key: string, target: EventTarget = document.body, init: KeyboardEventInit = {}) => target.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, ...init }))

describe('raccourcis clavier (alignés sur Gmail, WCAG 2.1.4)', () => {
  it('c ouvre Écrire, g puis i va aux mails, g puis s aux suivis', async () => {
    const { router } = await setup()
    press('c'); await flushPromises()
    expect(router.currentRoute.value.path).toBe('/ecrire')
    press('g'); press('i'); await flushPromises()
    expect(router.currentRoute.value.path).toBe('/mails')
    press('g'); press('s'); await flushPromises()
    expect(router.currentRoute.value.path).toBe('/suivis')
  })

  it('? ouvre l’aide', async () => {
    const { ui } = await setup()
    press('?')
    expect(ui.shortcutsOpen).toBe(true)
  })

  it('ne se déclenche jamais pendant la saisie dans un champ', async () => {
    const { w, router } = await setup()
    press('c', w.find('#field').element); await flushPromises()
    expect(router.currentRoute.value.path).toBe('/')
  })

  it('ne se déclenche pas avec Ctrl, Meta ou Alt (copier, rechercher…)', async () => {
    const { router } = await setup()
    press('c', document.body, { ctrlKey: true }); press('c', document.body, { metaKey: true }); press('c', document.body, { altKey: true })
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/')
  })

  it('ne se déclenche pas quand une fenêtre est ouverte', async () => {
    const { router } = await setup()
    const dlg = document.createElement('div'); dlg.setAttribute('role', 'dialog'); document.body.append(dlg)
    press('c'); await flushPromises()
    expect(router.currentRoute.value.path).toBe('/')
  })

  it('se désactive dans les réglages', async () => {
    const { router, settings } = await setup()
    settings.s.shortcuts = false
    press('c'); await flushPromises()
    expect(router.currentRoute.value.path).toBe('/')
  })

  it('g sans suite ne bloque rien', async () => {
    const { router } = await setup()
    press('g'); await new Promise((r) => setTimeout(r, 1300))
    press('i'); await flushPromises()
    expect(router.currentRoute.value.path).toBe('/')
  })
})

describe('aide des raccourcis', () => {
  it('liste chaque raccourci avec sa touche, et mentionne qu’on peut les désactiver', async () => {
    const { pinia } = await mountApp({ template: '<div/>' })
    useUiStore(pinia).shortcutsOpen = true
    mount(ShortcutsDialog, { global: { plugins: [pinia, i18n] }, attachTo: document.body })
    await flushPromises()
    const dlg = document.body.querySelector('[role="dialog"]')!
    expect(dlg.querySelectorAll('kbd').length).toBeGreaterThanOrEqual(12)
    expect(dlg.textContent).toContain('Écrire un message')
    expect(dlg.textContent).toContain('désactiver')
  })
})

describe('liste de mails : j / k', () => {
  it('j passe au message suivant, k au précédent', async () => {
    const { wrapper, router } = await mountApp(MailView, '/mails/m1', { id: 'm1' })
    await flushPromises()
    const order = wrapper.findAll('[data-mail]').map((e) => e.attributes('data-mail'))
    expect(order.length).toBeGreaterThan(2)
    await router.push({ name: 'mails', params: { id: order[0]! } })
    window.dispatchEvent(new CustomEvent('oscar:move', { detail: 1 }))
    await flushPromises()
    expect(router.currentRoute.value.params.id).toBe(order[1])
    window.dispatchEvent(new CustomEvent('oscar:move', { detail: -1 }))
    await flushPromises()
    expect(router.currentRoute.value.params.id).toBe(order[0])
    window.dispatchEvent(new CustomEvent('oscar:move', { detail: -1 }))
    await flushPromises()
    expect(router.currentRoute.value.params.id).toBe(order[0]) // pas de débordement au début
  })
})

describe('tri au clavier : e archiver, # corbeille, s suivre', () => {
  it('chaque touche émet l’action, jamais dans un champ de saisie', async () => {
    await setup()
    const seen: string[] = []
    const on = (e: Event) => seen.push((e as CustomEvent<string>).detail)
    window.addEventListener('oscar:act', on)
    press('e'); press('#'); press('s'); press('Delete')
    expect(new Set(seen)).toEqual(new Set(['archive', 'trash', 'follow']))
    const n = seen.length
    press('e', document.getElementById('field')!)
    expect(seen).toHaveLength(n)
    window.removeEventListener('oscar:act', on)
  })
})
