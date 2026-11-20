import { describe, expect, it } from 'vitest'
import { flagOf, groupByStatus, needsAction, relanceQueue, sortByPriority } from '../domain/suivi'
import type { Suivi } from '../domain/types'

const NOW = new Date(2026, 10, 20, 10, 0)
const mk = (id: string, p: Partial<Suivi> = {}): Suivi => ({
  id, subject: id, contact: 'x', direction: 'waiting', status: 'waiting', dueAt: '2026-11-30', relanceAt: null,
  relanceCount: 0, noRelance: false, responsible: 'Moi', mailbox: 'personal', mailId: null, replyReceived: false,
  suggestedByAi: false, reason: null, journal: [], ...p,
})

describe('flagOf', () => {
  it('une réponse reçue prime sur tout (F-76) mais jamais de clôture automatique (F-65)', () => {
    const s = mk('a', { replyReceived: true, dueAt: '2026-11-01' })
    expect(flagOf(s, NOW)).toBe('replyReceived')
    expect(s.status).toBe('waiting')
  })
  it('retard, puis relance due, puis échéance du jour', () => {
    expect(flagOf(mk('a', { dueAt: '2026-11-19' }), NOW)).toBe('late')
    expect(flagOf(mk('b', { relanceAt: '2026-11-20' }), NOW)).toBe('relanceDue')
    expect(flagOf(mk('c', { dueAt: '2026-11-20' }), NOW)).toBe('dueToday')
    expect(flagOf(mk('d'), NOW)).toBeNull()
  })
  it('« Ne plus relancer » supprime le signal de relance (F-55)', () => {
    expect(flagOf(mk('a', { relanceAt: '2026-11-18', noRelance: true }), NOW)).toBeNull()
  })
  it('un suivi traité ne signale rien', () => {
    expect(flagOf(mk('a', { status: 'done', dueAt: '2026-11-01' }), NOW)).toBeNull()
  })
})

describe('sortByPriority', () => {
  it('classe réponse reçue, retard, relance due, échéance du jour, reste, traités', () => {
    const out = sortByPriority(
      [
        mk('done', { status: 'done' }), mk('rest'), mk('today', { dueAt: '2026-11-20' }),
        mk('relance', { relanceAt: '2026-11-19' }), mk('late', { dueAt: '2026-11-10' }), mk('reply', { replyReceived: true }),
      ],
      NOW,
    )
    expect(out.map((s) => s.id)).toEqual(['reply', 'late', 'relance', 'today', 'rest', 'done'])
  })
})

describe('relanceQueue (F-63)', () => {
  const items = [mk('a', { relanceAt: '2026-11-18' }), mk('b', { relanceAt: '2026-11-15' }), mk('c', { relanceAt: '2026-11-19' })]
  it('trie par retard et respecte le plafond quotidien', () => {
    expect(relanceQueue(items, NOW, 2).map((s) => s.id)).toEqual(['b', 'a'])
  })
  it('tient compte des relances déjà proposées aujourd’hui', () => {
    expect(relanceQueue(items, NOW, 3, 3)).toEqual([])
  })
})

describe('needsAction / groupByStatus', () => {
  it('une simple attente ne réclame rien', () => {
    expect(needsAction(mk('a'), NOW)).toBe(false)
    expect(needsAction(mk('b', { dueAt: '2026-11-01' }), NOW)).toBe(true)
  })
  it('le tableau a les quatre états de F-51', () => {
    expect(groupByStatus([mk('a', { status: 'new' })]).map((c) => c.status)).toEqual(['new', 'inProgress', 'waiting', 'done'])
  })
})
