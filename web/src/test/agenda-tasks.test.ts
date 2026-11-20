import { describe, expect, it } from 'vitest'
import { nextEvent } from '../domain/agenda'
import { filterTasks, sortTasks, urgency } from '../domain/tasks'
import type { CalEvent, CalendarSource, Task } from '../domain/types'

const NOW = new Date(2026, 10, 20, 10, 0)
const SOURCES: CalendarSource[] = [
  { id: 'iut', label: 'IUT', kind: 'caldav', personal: false },
  { id: 'perso', label: 'Perso', kind: 'google', personal: true },
]
const ev = (id: string, cal: string, h: number, dur = 1): CalEvent => ({
  id, calendar: cal, title: id, start: new Date(2026, 10, 20, h).toISOString(), end: new Date(2026, 10, 20, h + dur).toISOString(), location: null,
})

describe('nextEvent (F-71)', () => {
  it('donne le RDV en cours puis le suivant', () => {
    const r = nextEvent([ev('a', 'iut', 9, 2), ev('b', 'iut', 14), ev('c', 'iut', 16)], SOURCES, NOW, false)
    expect([r.current?.id, r.next?.id, r.after?.id]).toEqual(['a', 'a', 'b'])
  })
  it('exclut l’agenda perso par défaut et l’inclut sur réglage', () => {
    const events = [ev('p', 'perso', 12), ev('b', 'iut', 15)]
    expect(nextEvent(events, SOURCES, NOW, false).next?.id).toBe('b')
    expect(nextEvent(events, SOURCES, NOW, true).next?.id).toBe('p')
  })
  it('signale quand il ne reste rien aujourd’hui', () => {
    expect(nextEvent([ev('old', 'iut', 7)], SOURCES, NOW, false).noneToday).toBe(true)
  })
})

const task = (id: string, p: Partial<Task> = {}): Task => ({
  id, title: id, description: '', dueAt: null, priority: 3, project: null, tags: [], done: false, origin: 'manual',
  toConfirm: false, source: null, ...p,
})

describe('tâches (F-32, F-33, F-34)', () => {
  const tasks = [task('a', { project: 'Jury', tags: ['x'], dueAt: '2026-11-25' }), task('b', { project: 'Stages', tags: ['x', 'y'], priority: 1 }), task('c', { done: true })]
  it('masque les traitées sauf demande', () => {
    expect(filterTasks(tasks, { projects: [], tags: [], showDone: false }).map((t) => t.id)).toEqual(['a', 'b'])
    expect(filterTasks(tasks, { projects: [], tags: [], showDone: true })).toHaveLength(3)
  })
  it('cumule projet et tags', () => {
    expect(filterTasks(tasks, { projects: ['Stages'], tags: ['x', 'y'], showDone: false }).map((t) => t.id)).toEqual(['b'])
    expect(filterTasks(tasks, { projects: ['Jury'], tags: ['y'], showDone: false })).toEqual([])
  })
  it('trie par échéance ou priorité', () => {
    expect(sortTasks(tasks, 'due').map((t) => t.id)[0]).toBe('a')
    expect(sortTasks(tasks, 'priority').map((t) => t.id)[0]).toBe('b')
  })
  it('l’urgence est calculée, le retard pèse le plus', () => {
    expect(urgency(task('l', { dueAt: '2026-11-01' }), NOW)).toBeGreaterThan(urgency(task('p', { priority: 1 }), NOW))
  })
})

import { conflictIds } from '../domain/agenda'
describe('conflits (F-21)', () => {
  it('repère les chevauchements, pas les rendez-vous qui s’enchaînent', () => {
    const ids = conflictIds([ev('a', 'iut', 9, 2), ev('b', 'iut', 10), ev('c', 'iut', 11)])
    expect([...ids].sort()).toEqual(['a', 'b'])
  })
})
