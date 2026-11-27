import { flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { api } from '../api'
import AdminAiUsage from '../components/admin/AdminAiUsage.vue'
import { mountApp } from './helpers'

describe('indicateurs d’usage de l’IA (administration)', () => {
  it('dit combien un modèle, les règles et les replis, sans rien nommer, et change de période', async () => {
    const { wrapper } = await mountApp(AdminAiUsage, '/administration')
    await flushPromises()
    expect(wrapper.text()).toContain('Demandes à Oscar')
    expect(wrapper.text()).toContain('3 personne(s) sur 4')
    expect(wrapper.findAll('tbody tr').map((r) => r.find('th').text())).toEqual(['Suggestions à la lecture', 'Brouillons de réponse'])
    const spy = vi.spyOn(api.instance, 'aiUsage')
    await wrapper.findAll('button').find((b) => b.text() === '7 jours')!.trigger('click')
    await flushPromises()
    expect(spy).toHaveBeenCalledWith(7)
  })
})
