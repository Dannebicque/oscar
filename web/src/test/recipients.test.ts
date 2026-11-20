import { flushPromises, mount } from '@vue/test-utils'
import axe from 'axe-core'
import { afterEach, describe, expect, it } from 'vitest'
import { h, ref } from 'vue'
import RecipientInput from '../components/ui/RecipientInput.vue'
import { searchDirectory } from '../domain/directory'
import { i18n } from '../i18n'
import { addRecipient } from './helpers'

afterEach(() => { document.body.innerHTML = '' })

function field(initial: string[] = []) {
  const value = ref(initial)
  const w = mount(() => h(RecipientInput, { id: 'to', label: 'À', modelValue: value.value, 'onUpdate:modelValue': (v: string[]) => (value.value = v) }), { global: { plugins: [i18n] }, attachTo: document.body })
  return { w, value }
}

describe('annuaire (F-18)', () => {
  it('cherche sans tenir compte des accents ni de la casse, sur le nom et l’adresse', () => {
    expect(searchDirectory('lea').map((e) => e.name)).toEqual(['Léa Martin'])
    expect(searchDirectory('LÉA')[0].name).toBe('Léa Martin')
    expect(searchDirectory('iut-troyes').length).toBeGreaterThanOrEqual(3)
    expect(searchDirectory('   ')).toEqual([])
    expect(searchDirectory('zzz')).toEqual([])
    expect(searchDirectory('c', 2)).toHaveLength(2)
  })
})

describe('destinataires (Reka UI)', () => {
  it('Entrée valide une adresse, avec ou sans nom, et la met en minuscules', async () => {
    const { w, value } = field()
    await addRecipient(w, 'to', 'Sophie Laurent <Sophie@X.fr>')
    await addRecipient(w, 'to', 'marc@y.fr')
    expect(value.value).toEqual(['sophie@x.fr', 'marc@y.fr'])
  })

  it('refuse une adresse invalide en l’annonçant, sans l’ajouter', async () => {
    const { w, value } = field()
    await addRecipient(w, 'to', 'pas une adresse')
    expect(value.value).toEqual([])
    expect(w.find('[role="alert"]').text()).toContain('pas une adresse')
    expect(w.find('#to').attributes('aria-invalid')).toBe('true')
  })

  it('propose l’annuaire dans une liste annoncée, navigable aux flèches, et ajoute avec Entrée', async () => {
    const { w, value } = field()
    const input = w.find('#to')
    expect(input.attributes('role')).toBe('combobox')
    expect(input.attributes('aria-expanded')).toBe('false')
    await input.setValue('clai')
    await flushPromises()
    expect(input.attributes('aria-expanded')).toBe('true')
    const options = w.findAll('[role="option"]')
    expect(options.map((o) => o.find('strong').text())).toEqual(['Claire Petit', 'Claire Roux'])
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(input.attributes('aria-activedescendant')).toBe(options[1].attributes('id'))
    expect(options[1].attributes('aria-selected')).toBe('true')
    await input.trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(value.value).toEqual(['claire.roux@iut-troyes.fr'])
    expect((input.element as HTMLInputElement).value).toBe('')
    expect(input.attributes('aria-expanded')).toBe('false')
  })

  it('Échap referme les suggestions sans rien ajouter', async () => {
    const { w, value } = field()
    const input = w.find('#to')
    await input.setValue('lea')
    await flushPromises()
    expect(w.findAll('[role="option"]')).toHaveLength(1)
    await input.trigger('keydown', { key: 'Escape' })
    expect(input.attributes('aria-expanded')).toBe('false')
    expect(value.value).toEqual([])
  })

  it('un clic sur une suggestion l’ajoute', async () => {
    const { w, value } = field()
    await w.find('#to').setValue('scol')
    await flushPromises()
    await w.find('[role="option"]').trigger('mousedown')
    expect(value.value).toEqual(['scolarite@iut-troyes.fr'])
  })

  it('dit quand aucun contact ne correspond', async () => {
    const { w } = field()
    await w.find('#to').setValue('zzz')
    await flushPromises()
    expect(w.find('[role="status"]').text()).toContain('Aucun contact')
  })

  it('ignore un doublon', async () => {
    const { w, value } = field(['a@x.fr'])
    await addRecipient(w, 'to', 'A@x.fr')
    expect(value.value).toEqual(['a@x.fr'])
  })

  it('chaque pastille se retire avec un bouton nommé', async () => {
    const { w, value } = field(['a@x.fr', 'b@x.fr'])
    const del = w.find('button[aria-label="Retirer a@x.fr"]')
    expect(del.exists()).toBe(true)
    await del.trigger('click')
    await flushPromises()
    expect(value.value).toEqual(['b@x.fr'])
  })

  it('affiche le nom de l’annuaire à côté de l’adresse connue', async () => {
    const { w } = field(['lea.martin@iut-troyes.fr'])
    expect(w.text()).toContain('Léa Martin · lea.martin@iut-troyes.fr')
  })

  it('aucune violation d’accessibilité (champ nommé, pastilles, boutons)', async () => {
    const host = document.createElement('main')
    document.body.append(host)
    const value = ref(['a@x.fr'])
    mount(() => h('div', [h('label', { for: 'to' }, 'À'), h(RecipientInput, { id: 'to', label: 'À', modelValue: value.value, 'onUpdate:modelValue': (v: string[]) => (value.value = v) })]), { global: { plugins: [i18n] }, attachTo: host })
    const r = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false } }, runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] })
    expect(r.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([])
  })
})
