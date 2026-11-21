import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import ComposeView from '../views/ComposeView.vue'
import SettingsView from '../views/SettingsView.vue'
import SuivisView from '../views/SuivisView.vue'
import { DEFAULTS, parseSettings, useSettingsStore } from '../stores/settings'
import { useMailsStore } from '../stores/mails'
import { useSuivisStore } from '../stores/suivis'
import { useUiStore } from '../stores/ui'
import { resolveTheme } from '../themes'
import { addRecipient, mountApp } from './helpers'

afterEach(() => { document.body.innerHTML = '' })

describe('thèmes et réglages (U-09, F-17)', () => {
  it('résout « automatique » selon la préférence système', () => {
    expect(resolveTheme('system', true)).toBe('dark')
    expect(resolveTheme('system', false)).toBe('light')
    expect(resolveTheme('contrast', false)).toBe('contrast')
  })
  it('ignore des réglages stockés corrompus ou invalides', () => {
    expect(parseSettings('pas du json')).toEqual(DEFAULTS)
    expect(parseSettings(JSON.stringify({ theme: 'violet', dailyCap: 'beaucoup', density: 'compact' }))).toMatchObject({ theme: 'system', dailyCap: 5, density: 'compact' })
  })
  it('changer de thème et de densité dans Réglages s’applique au document', async () => {
    const { wrapper } = await mountApp(SettingsView)
    // Reka UI : un groupe de boutons role="radio".
    await wrapper.find('button[role="radio"][value="dark"]').trigger('click')
    await wrapper.find('button[role="radio"][value="compact"]').trigger('click')
    await flushPromises()
    expect(document.documentElement.dataset.theme).toBe('dark')
    expect(document.documentElement.dataset.density).toBe('compact')
    expect(useSettingsStore().s.theme).toBe('dark')
  })
})

describe('Suivis', () => {
  it('met en tête la réponse reçue et dit l’état en texte (jamais par la couleur seule)', async () => {
    const { wrapper } = await mountApp(SuivisView, '/suivis')
    const first = wrapper.find('ol li')
    expect(first.text()).toContain('Planning des soutenances')
    expect(first.text()).toContain('Réponse reçue')
  })
  it('clore un suivi est un clic, annulable, jamais automatique (F-65)', async () => {
    const { wrapper } = await mountApp(SuivisView, '/suivis')
    const store = useSuivisStore()
    const ui = useUiStore()
    const row = wrapper.findAll('ol li').find((li) => li.text().includes('Planning des soutenances'))!
    await row.findAll('button').find((b) => b.text().includes('Clore'))!.trigger('click')
    await flushPromises()
    expect(store.byId('s1')?.status).toBe('done')
    expect(ui.toasts.at(-1)?.undo).toBeTypeOf('function')
    await ui.toasts.at(-1)!.undo!()
    await flushPromises()
    expect(store.byId('s1')?.status).toBe('new')
  })
  it('la vue Équipe ne montre que les demandes de l’alias, sans responsable désigné', async () => {
    const { wrapper } = await mountApp(SuivisView, '/suivis')
    const store = useSuivisStore()
    store.scope = 'team'
    await flushPromises()
    const text = wrapper.find('ol').text()
    expect(text).toContain('Question d’inscription')
    expect(text).toContain('Pris par Claire')
    expect(text).toContain('Personne ne l’a pris')
    expect(text).not.toContain('Planning des soutenances')
    expect(store.inScope.every((s) => s.responsible === null)).toBe(true)
  })
})

describe('boîte partagée (F-67, F-68)', () => {
  it('prendre une demande informe le suivi lié et le journal', async () => {
    await mountApp(SuivisView, '/suivis')
    const mails = useMailsStore()
    const suivis = useSuivisStore()
    await mails.load()
    await suivis.load()
    await mails.take('c2')
    expect(mails.byId('c2')?.claim).toEqual({ state: 'taken', by: 'David' })
    expect(suivis.byId('s7')?.status).toBe('inProgress')
    expect(suivis.byId('s7')?.responsible).toBeNull()
    expect(mails.byId('c2')?.activity.at(-1)?.action).toBe('take')
  })
})

describe('Écrire (F-73)', () => {
  it('« Suivre la réponse » est préremplie par Oscar, modifiable, et crée un suivi à l’envoi', async () => {
    const { wrapper, router } = await mountApp(ComposeView, '/ecrire')
    const box = wrapper.find('[role="checkbox"]')
    expect(box.attributes('aria-checked')).toBe('true')
    expect(wrapper.text()).toContain('Suggéré par l’IA')
    await addRecipient(wrapper, 'to', 'Sophie Laurent <sophie@x.fr>')
    await wrapper.find('#subject').setValue('Ma question')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    const s = useSuivisStore().items.find((x) => x.subject === 'Ma question')
    expect(s).toMatchObject({ direction: 'waiting', contact: 'Sophie', suggestedByAi: true })
    expect(router.currentRoute.value.name).toBe('mails')
  })
  it('refuse un envoi sans destinataire ni objet, avec une erreur annoncée', async () => {
    const { wrapper } = await mountApp(ComposeView, '/ecrire')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.findAll('[role="alert"]').map((a) => a.text()).join(' | ')).toContain('au moins un destinataire')
    expect(useSuivisStore().items.length).toBe(0)
  })
})

describe('fil de conversation (F-50)', () => {
  it('relie une réponse au message d’origine et à ses voisins, du plus ancien au plus récent', async () => {
    const { setActivePinia, createPinia } = await import('pinia')
    setActivePinia(createPinia())
    const { useMailsStore } = await import('../stores/mails')
    const s = useMailsStore()
    const base = { mailbox: 'personal', from: { name: 'A', address: 'a@x.fr' }, to: [], subject: 's', paragraphs: [], unread: false, attachments: [], suivi: null, claim: null, composing: null, notes: [], activity: [], hints: {} } as never
    const mk = (id: string, messageId: string, parents: string[], at: string, folder: 'inbox' | 'sent') => ({ ...(base as object), id, messageId, parents, at, folder }) as never
    s.items = [mk('1', '<a>', [], '2026-11-21T09:00:00Z', 'sent'), mk('2', '<b>', ['<a>'], '2026-11-21T10:00:00Z', 'inbox'), mk('3', '<z>', [], '2026-11-21T11:00:00Z', 'inbox')]
    expect(s.threadOf(s.items[1]).map((m) => m.id)).toEqual(['1', '2'])
  })
})
