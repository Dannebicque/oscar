import { describe, expect, it } from 'vitest'
import { needsAction, sortByPriority } from '../domain/priority'
import type { Suivi } from '../domain/types'

const mk = (id: string, state: Suivi['state'], dueAt = '2026-11-20'): Suivi => ({
  id, subject: id, contact: 'x', state, dueAt, suggestedByAi: false,
})

describe('sortByPriority', () => {
  it('met les réponses reçues avant les relances et les suivis terminés en dernier', () => {
    const out = sortByPriority([mk('a', 'done'), mk('b', 'relanceDue'), mk('c', 'toHandle'), mk('d', 'waiting')])
    expect(out.map((s) => s.id)).toEqual(['c', 'b', 'd', 'a'])
  })
  it("départage à l'échéance la plus proche", () => {
    const out = sortByPriority([mk('a', 'late', '2026-11-18'), mk('b', 'late', '2026-11-16')])
    expect(out.map((s) => s.id)).toEqual(['b', 'a'])
  })
})

describe('needsAction', () => {
  it("n'alerte pas sur un suivi en attente ou terminé", () => {
    expect(needsAction(mk('a', 'waiting'))).toBe(false)
    expect(needsAction(mk('b', 'done'))).toBe(false)
    expect(needsAction(mk('c', 'late'))).toBe(true)
  })
})
