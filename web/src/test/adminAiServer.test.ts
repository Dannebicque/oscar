import { flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { api } from '../api'
import AdminAiServer from '../components/admin/AdminAiServer.vue'
import { mountApp } from './helpers'

describe('modèle de l’établissement (administration)', () => {
  it('prévient, exige la confirmation d’hébergement, puis enregistre sans réafficher la clé', async () => {
    const { wrapper } = await mountApp(AdminAiServer, '/administration')
    await flushPromises()
    expect(wrapper.text()).toContain('sera envoyé à ce serveur')
    expect(wrapper.text()).toContain('Aucun modèle branché')
    await wrapper.find('#ais-url').setValue('https://ia.univ.example/v1')
    await wrapper.find('#ais-model').setValue('llama3.1')
    await wrapper.find('#ais-key').setValue('CLE')
    const save = () => wrapper.findAll('button').find((b) => b.text() === 'Enregistrer')!
    await save().trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Confirme que ce serveur est hébergé')

    const spy = vi.spyOn(api.instance, 'saveAiServer')
    await wrapper.find('#ais-hosted').trigger('click')
    await save().trigger('click')
    await flushPromises()
    expect(spy).toHaveBeenCalledWith(expect.objectContaining({ hostedByUs: true, apiKey: 'CLE', model: 'llama3.1' }))
    expect(wrapper.text()).toContain('Un modèle est branché')
    expect((wrapper.find('#ais-key').element as HTMLInputElement).value).toBe('')
  })
})
