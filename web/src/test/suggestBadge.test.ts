import { describe, expect, it } from 'vitest'
import SuggestBadge from '../components/ui/SuggestBadge.vue'
import { mountApp } from './helpers'

describe('pastille des propositions d’Oscar', () => {
  it('violette dans tous les cas, « IA » seulement quand un modèle a répondu', async () => {
    const rules = (await mountApp(SuggestBadge, '/', {})).wrapper
    expect(rules.text()).toBe('Suggestion')
    const model = (await mountApp(SuggestBadge, '/', { ai: true })).wrapper
    expect(model.text()).toBe('Suggéré par l’IA')
    expect(rules.html()).toContain('data-tone="ai"')
    expect(model.html()).toContain('data-tone="ai"')
  })
})
