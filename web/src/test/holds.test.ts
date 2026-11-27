import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api'
import HoldsPanel from '../components/agenda/HoldsPanel.vue'
import { holdSignal, overlapping } from '../domain/holds'
import type { SlotHold, Suivi } from '../domain/types'
import { useAgendaStore } from '../stores/agenda'
import { useMailsStore } from '../stores/mails'
import { mountApp } from './helpers'

const NOW = new Date('2026-11-27T10:00:00Z')
const hold = (id: string, start: string, patch: Partial<SlotHold> = {}): SlotHold => ({
  id, groupId: 'g1', start, end: start.replace('T09', 'T10').replace('T13', 'T14'), contact: 'Claire Petit', subject: 'Re : Disponibilités',
  mailId: '<sent@x>', repliedToMailId: '<q@x>', suiviId: null, createdAt: '2026-11-27T08:00:00Z', ...patch,
})

describe('créneaux en provisoire (F-74)', () => {
  afterEach(() => vi.restoreAllMocks())

  it('dit où on en est : répondu, clos, sans nouvelle, en attente', () => {
    const h = hold('a', '2026-12-01T09:00:00Z')
    expect(holdSignal(h, undefined, false, NOW)).toBe('pending')
    expect(holdSignal(h, undefined, true, NOW)).toBe('replied')
    expect(holdSignal(h, { replyReceived: true, status: 'new' } as Suivi, false, NOW)).toBe('replied')
    expect(holdSignal(h, { replyReceived: true, status: 'done' } as Suivi, true, NOW)).toBe('closed')
    expect(holdSignal(h, undefined, false, new Date('2026-12-04T10:00:00Z'))).toBe('stale')
  })

  it('compare les instants, pas les textes', () => {
    expect(overlapping({ start: '2026-12-01T10:00:00+01:00', end: '2026-12-01T11:00:00+01:00' }, { start: '2026-12-01T09:30:00Z', end: '2026-12-01T09:45:00Z' })).toBe(true)
    expect(overlapping({ start: '2026-12-01T09:00:00Z', end: '2026-12-01T10:00:00Z' }, { start: '2026-12-01T10:00:00Z', end: '2026-12-01T11:00:00Z' })).toBe(false)
  })

  async function panel() {
    const list = [hold('a', '2026-12-01T09:00:00Z'), hold('b', '2026-12-02T13:00:00Z')]
    const { wrapper } = await mountApp(HoldsPanel, '/', { holds: list })
    const agenda = useAgendaStore()
    agenda.holds = list
    agenda.sources = [{ id: 'iut', label: 'IUT', personal: false, kind: 'caldav' } as never]
    await flushPromises()
    const button = (text: string) => wrapper.findAll('button').filter((b) => b.text() === text)
    return { wrapper, agenda, button }
  }

  it('retenir écrit l’événement puis libère la proposition', async () => {
    const { agenda, button, wrapper } = await panel()
    const create = vi.spyOn(api.agenda, 'create').mockImplementation(async (e) => ({ ...e, id: 'new' }))
    const release = vi.spyOn(api.agenda, 'releaseHoldGroup').mockResolvedValue()
    expect(wrapper.text()).toContain('Proposé à Claire Petit')
    await button('Retenir')[0].trigger('click')
    await flushPromises()
    expect(create).toHaveBeenCalledWith(expect.objectContaining({ calendar: 'iut', title: 'Disponibilités · Claire Petit', start: '2026-12-01T09:00:00Z' }))
    expect(release).toHaveBeenCalledWith('g1')
    expect(agenda.holds).toEqual([])
  })

  it('si l’agenda refuse, rien n’est libéré', async () => {
    const { agenda, button } = await panel()
    vi.spyOn(api.agenda, 'create').mockRejectedValue(new Error('caldav'))
    const release = vi.spyOn(api.agenda, 'releaseHoldGroup')
    await button('Retenir')[0].trigger('click')
    await flushPromises()
    expect(release).not.toHaveBeenCalled()
    expect(agenda.holds).toHaveLength(2)
  })

  it('libérer retire un seul créneau', async () => {
    const { agenda, button } = await panel()
    const release = vi.spyOn(api.agenda, 'releaseHold').mockResolvedValue()
    await button('Libérer')[1].trigger('click')
    await flushPromises()
    expect(release).toHaveBeenCalledWith('b')
    expect(agenda.holds.map((h) => h.id)).toEqual(['a'])
  })

  it('un envoi avec créneaux les fait apparaître dans l’agenda', async () => {
    await mountApp({ template: '<div/>' })
    const agenda = useAgendaStore()
    await useMailsStore().send({ to: ['claire@x.fr'], cc: [], bcc: [], subject: 'Re : Dispo', body: 'Voici', holds: [{ start: '2027-01-05T09:00:00Z', end: '2027-01-05T10:00:00Z' }] })
    expect(agenda.holds).toHaveLength(1)
  })
})
