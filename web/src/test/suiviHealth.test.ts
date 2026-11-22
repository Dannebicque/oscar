import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { computeHealth, durationParts, median } from '../domain/suiviHealth'
import type { Suivi } from '../domain/types'
import { useSuivisStore } from '../stores/suivis'
import SuivisView from '../views/SuivisView.vue'
import { mountApp } from './helpers'

afterEach(() => { document.body.innerHTML = '' })

const now = new Date('2026-11-22T12:00:00Z')
const ago = (days: number, hours = 0) => new Date(now.getTime() - (days * 24 + hours) * 3_600_000).toISOString()
const ask = (id: string, contact: string, sentDaysAgo: number, replyAfterH: number | null, over: Partial<Suivi> = {}): Suivi => ({
  id, subject: id, contact, direction: 'waiting', status: replyAfterH === null ? 'waiting' : 'done', dueAt: '2026-12-01', relanceAt: null, relanceCount: 0, noRelance: false, responsible: 'David',
  mailbox: 'personal', mailId: id, replyReceived: false, suggestedByAi: false, reason: null,
  journal: [{ at: ago(sentDaysAgo), author: 'David', action: 'sent' }, ...(replyAfterH === null ? [] : [{ at: ago(sentDaysAgo, -replyAfterH), author: contact, action: 'replyReceived' }])], ...over,
})

describe('bilan des suivis', () => {
  it('médiane et durées', () => {
    expect(median([])).toBeNull()
    expect(median([3, 1, 2])).toBe(2)
    expect(median([1, 2, 3, 4])).toBe(2.5)
    expect(durationParts(26 * 3_600_000)).toEqual({ days: 1, hours: 2 })
  })

  it('taux de réponse, délai médian, âge des demandes ouvertes et relances', () => {
    const h = computeHealth([
      ask('a', 'Claire', 10, 4), ask('b', 'Claire', 8, 28), ask('c', 'Marc', 20, null, { relanceCount: 2 }), ask('d', 'Marc', 5, null), ask('e', 'Marc', 1, null),
    ], now, 90)
    expect(h).toMatchObject({ asked: 5, answered: 2, open: 3, relances: 2 })
    expect(h.rate).toBeCloseTo(0.4)
    expect(h.medianMs).toBe(16 * 3_600_000)
    expect(h.ages).toEqual({ d0_2: 1, d3_7: 1, d8_14: 0, d15: 1 })
  })

  it('classe les contacts : jamais de réponse d’abord, puis les lents, puis les rapides', () => {
    const h = computeHealth([
      ask('a', 'Rapide', 6, 2), ask('b', 'Lent', 9, 24 * 5), ask('c', 'Muet', 12, null), ask('d', 'Muet', 4, null), ask('e', 'Nouveau', 1, null),
    ], now, 90)
    expect(h.contacts.map((c) => [c.contact, c.verdict])).toEqual([['Muet', 'silent'], ['Lent', 'slow'], ['Nouveau', 'waiting'], ['Rapide', 'fast']])
  })

  it('la période écarte les anciennes demandes, la boîte partagée et les mails reçus à traiter', () => {
    const list = [ask('old', 'Claire', 70, 3), ask('new', 'Claire', 5, 3), ask('shared', 'Equipe', 3, null, { mailbox: 'contact' }), ask('in', 'Sophie', 3, null, { direction: 'toHandle', journal: [{ at: ago(3), author: 'Sophie', action: 'created' }] })]
    expect(computeHealth(list, now, 30).asked).toBe(1)
    expect(computeHealth(list, now, 0).asked).toBe(2)
    expect(computeHealth([], now, 90)).toMatchObject({ asked: 0, rate: null, medianMs: null })
  })

  it('l’onglet Bilan s’affiche, lit les suivis et reste muet sur les reproches', async () => {
    const { wrapper } = await mountApp(SuivisView, '/suivis')
    await flushPromises()
    const tab = [...wrapper.findAll('[role="radio"], button')].find((b) => b.text() === 'Bilan')!
    await tab.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Bilan de tes demandes')
    expect(wrapper.text()).toContain('Réponses reçues')
    expect(wrapper.find('table').text()).toContain('Service achats')
    expect(wrapper.text()).not.toMatch(/faute|retard de|mauvais/i)
    expect(useSuivisStore().items.some((s) => s.id === 'h1')).toBe(true)
  })
})
