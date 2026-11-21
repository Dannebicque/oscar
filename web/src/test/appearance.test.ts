import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import AppearancePanel from '../components/settings/AppearancePanel.vue'
import { DEFAULTS, parseSettings, useSettingsStore } from '../stores/settings'
import { mountApp } from './helpers'

beforeEach(() => { localStorage.clear(); document.documentElement.removeAttribute('style') })
afterEach(() => { document.body.innerHTML = '' })

describe('apparence : style, palette, accent libre', () => {
  it('ignore des réglages d’apparence invalides', () => {
    const s = parseSettings(JSON.stringify({ style: 'cyberpunk', palette: 'violet', customAccent: 'rouge', theme: 'dark' }))
    expect([s.style, s.palette, s.customAccent, s.theme]).toEqual([DEFAULTS.style, DEFAULTS.palette, DEFAULTS.customAccent, 'dark'])
  })

  it('choisir le style Rétro change la forme et la police de toute l’application', async () => {
    const { wrapper } = await mountApp(AppearancePanel)
    await wrapper.find('button[role="radio"][value="retro"]').trigger('click')
    await flushPromises()
    const root = document.documentElement
    expect(root.dataset.style).toBe('retro')
    expect(root.style.getPropertyValue('--bw')).toBe('2px')
    expect(root.style.getPropertyValue('--font-sans')).toContain('JetBrains Mono')
    expect(JSON.parse(localStorage.getItem('oscar.settings')!).style).toBe('retro')
  })

  it('chaque style et chaque palette est proposé, avec un aperçu fidèle', async () => {
    const { wrapper } = await mountApp(AppearancePanel)
    const radios = wrapper.findAll('button[role="radio"]').map((r) => r.attributes('value'))
    for (const v of ['modern', 'retro', 'neon', 'azur', 'emeraude', 'ambre', 'corail', 'graphite', 'custom']) expect(radios).toContain(v)
    expect(wrapper.findAll('.tp')).toHaveLength(3)
  })

  it('une couleur libre trop pâle est corrigée, et l’interface le dit', async () => {
    const { wrapper } = await mountApp(AppearancePanel)
    useSettingsStore().s.theme = 'light'
    await wrapper.find('button[role="radio"][value="custom"]').trigger('click')
    const hex = wrapper.find('input.hex')
    ;(hex.element as HTMLInputElement).value = '#ffee88'
    await hex.trigger('change')
    await flushPromises()
    expect(useSettingsStore().s.customAccent).toBe('#ffee88')
    const status = wrapper.find('p[role="status"]').text()
    expect(status).toContain('Ajustée en')
    expect(status).toMatch(/\d\.\d:1/)
    // le bouton principal est bien celui qui a été corrigé, pas la couleur brute
    expect(document.documentElement.style.getPropertyValue('--c-primary')).not.toBe('#ffee88')
  })

  it('une couleur déjà lisible est conservée', async () => {
    const { wrapper } = await mountApp(AppearancePanel)
    useSettingsStore().s.theme = 'light'
    await wrapper.find('button[role="radio"][value="custom"]').trigger('click')
    const hex = wrapper.find('input.hex')
    ;(hex.element as HTMLInputElement).value = '#1d4ed8'
    await hex.trigger('change')
    await flushPromises()
    expect(wrapper.find('p[role="status"]').text()).toContain('Lisible')
  })

  it('ignore un code couleur invalide', async () => {
    const { wrapper } = await mountApp(AppearancePanel)
    const before = useSettingsStore().s.customAccent
    await wrapper.find('button[role="radio"][value="custom"]').trigger('click')
    const hex = wrapper.find('input.hex')
    ;(hex.element as HTMLInputElement).value = 'pas une couleur'
    await hex.trigger('change')
    expect(useSettingsStore().s.customAccent).toBe(before)
  })

  it('le mode contraste élevé impose son jaune quel que soit le style', async () => {
    const { wrapper } = await mountApp(AppearancePanel)
    useSettingsStore().s.theme = 'contrast'
    await flushPromises()
    for (const st of ['modern', 'retro', 'neon']) {
      await wrapper.find(`button[role="radio"][value="${st}"]`).trigger('click')
      expect(document.documentElement.style.getPropertyValue('--c-primary')).toBe('#ffe14d')
    }
  })
})
