import axe from 'axe-core'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import BaseDialog from '../components/ui/BaseDialog.vue'
import RadioGroup from '../components/ui/RadioGroup.vue'
import SegmentedControl from '../components/ui/SegmentedControl.vue'
import SwitchField from '../components/ui/SwitchField.vue'
import ToastHost from '../components/ui/ToastHost.vue'
import { i18n } from '../i18n'
import { useUiStore } from '../stores/ui'
import { mountApp } from './helpers'

afterEach(() => { document.body.innerHTML = '' })
const plugins = [i18n]

async function audit() {
  const r = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } }, runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] })
  return r.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)
}

describe('fenêtre modale (Reka UI)', () => {
  const Host = defineComponent({
    components: { BaseDialog },
    setup() {
      const open = ref(false)
      return () => h('div', [
        h('button', { id: 'opener', onClick: () => (open.value = true) }, 'Ouvrir'),
        h(BaseDialog, { open: open.value, title: 'Titre', onClose: () => (open.value = false) }, { default: () => h('input', { id: 'inside', 'aria-label': 'Champ' }), footer: () => h('button', { id: 'ok' }, 'OK') }),
      ])
    },
  })

  it('porte le rôle dialog et un nom accessible, sans violation axe', async () => {
    const w = mount(Host, { global: { plugins }, attachTo: document.body })
    await w.find('#opener').trigger('click')
    await flushPromises()
    const dlg = document.body.querySelector('[role="dialog"]')!
    expect(dlg).toBeTruthy()
    expect(document.getElementById(dlg.getAttribute('aria-labelledby')!)?.textContent).toBe('Titre')
    expect(await audit()).toEqual([])
  })

  it('met le focus dans la fenêtre, se ferme avec Échap et rend le focus au bouton d’origine', async () => {
    const w = mount(Host, { global: { plugins }, attachTo: document.body })
    ;(w.find('#opener').element as HTMLElement).focus()
    await w.find('#opener').trigger('click')
    await flushPromises()
    await nextTick()
    const dlg = document.body.querySelector('[role="dialog"]')!
    expect(dlg.contains(document.activeElement)).toBe(true)
    dlg.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()
    await new Promise((r) => setTimeout(r, 30))
    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
    expect(document.activeElement?.id).toBe('opener')
  })

  it('le bouton de fermeture a un nom accessible', async () => {
    const w = mount(Host, { global: { plugins }, attachTo: document.body })
    await w.find('#opener').trigger('click')
    await flushPromises()
    expect(document.body.querySelector('.dlg-x')?.getAttribute('aria-label')).toBe('Fermer la fenêtre')
  })
})

describe('interrupteur, choix exclusifs, vues (Reka UI)', () => {
  it('l’interrupteur est un role="switch" relié à son libellé, et bascule au clic', async () => {
    const on = ref(false)
    const w = mount(() => h(SwitchField, { modelValue: on.value, label: 'Recevoir par e-mail', help: 'Aide', 'onUpdate:modelValue': (v: boolean) => (on.value = v) }), { global: { plugins }, attachTo: document.body })
    const sw = w.find('[role="switch"]')
    expect(sw.attributes('aria-checked')).toBe('false')
    expect(document.querySelector(`label[for="${sw.attributes('id')}"]`)?.textContent).toBe('Recevoir par e-mail')
    expect(sw.attributes('aria-describedby')).toBeTruthy()
    await sw.trigger('click')
    expect(on.value).toBe(true)
    expect(await audit()).toEqual([])
  })

  it('le groupe de choix expose radiogroup, radio et aria-checked', async () => {
    const v = ref('b')
    const w = mount(() => h(RadioGroup, { modelValue: v.value, legend: 'Thème', name: 't', options: [{ value: 'a', label: 'A' }, { value: 'b', label: 'B' }], 'onUpdate:modelValue': (x: string | number) => (v.value = String(x)) }), { global: { plugins }, attachTo: document.body })
    expect(w.find('[role="radiogroup"]').exists()).toBe(true)
    const radios = w.findAll('[role="radio"]')
    expect(radios.map((r) => r.attributes('aria-checked'))).toEqual(['false', 'true'])
    await radios[0].trigger('click')
    expect(v.value).toBe('a')
    expect(await audit()).toEqual([])
  })

  it('la liste de vues garde toujours un choix actif (on ne peut pas décocher)', async () => {
    const v = ref('x')
    const w = mount(() => h(SegmentedControl, { modelValue: v.value, label: 'Vue', options: [{ value: 'x', label: 'X' }, { value: 'y', label: 'Y' }], 'onUpdate:modelValue': (n: string) => (v.value = n) }), { global: { plugins }, attachTo: document.body })
    const items = w.findAll('button')
    await items[0].trigger('click') // re-cliquer l'actif ne le décoche pas
    expect(v.value).toBe('x')
    await items[1].trigger('click')
    expect(v.value).toBe('y')
    expect(w.find('[aria-label="Vue"]').exists()).toBe(true)
    expect(await audit()).toEqual([])
  })
})

describe('notifications (Reka UI)', () => {
  it('affiche le message, propose Annuler qui exécute l’annulation et ferme', async () => {
    const { pinia } = await mountApp({ template: '<div/>' })
    mount(ToastHost, { global: { plugins: [pinia, i18n] }, attachTo: document.body })
    const ui = useUiStore(pinia)
    let undone = false
    ui.notify('Suivi clos : « Devis ».', { undo: () => { undone = true } })
    await flushPromises()
    await nextTick()
    expect(document.body.textContent).toContain('Suivi clos : « Devis ».')
    const undo = [...document.body.querySelectorAll('button')].find((b) => b.textContent?.includes('Annuler'))!
    undo.click()
    await flushPromises()
    expect(undone).toBe(true)
    expect(ui.toasts).toHaveLength(0)
  })

  it('une erreur est annoncée de façon assertive et reste jusqu’à fermeture', async () => {
    const { pinia } = await mountApp({ template: '<div/>' })
    mount(ToastHost, { global: { plugins: [pinia, i18n] }, attachTo: document.body })
    useUiStore(pinia).notify('Le message n’est pas parti.', { tone: 'error' })
    await flushPromises()
    await new Promise((r) => setTimeout(r, 60)) // Reka annonce au prochain rendu
    await nextTick()
    const region = [...document.body.querySelectorAll('[aria-live]')].find((e) => e.textContent?.includes('pas parti'))
    expect(region).toBeTruthy()
    expect(region!.getAttribute('aria-live')).toBe('assertive')
  })
})
