import { describe, expect, it } from 'vitest'
import { briefFor } from '../domain/meetingBrief'
import type { CalEvent, Mail } from '../domain/types'

const now = new Date('2026-11-21T10:00:00Z')
const mail = (id: string, over: Partial<Mail>): Mail => ({
  id, mailbox: 'personal', folder: 'inbox', from: { name: 'Sophie Laurent', address: 'sophie@univ.fr' }, to: [{ name: 'David', address: 'david@iut.fr' }],
  subject: 'Planning', at: '2026-11-20T10:00:00Z', unread: false, paragraphs: [], attachments: [], suivi: null, hints: {}, ...over,
} as Mail)
const ev = (over: Partial<CalEvent> = {}): CalEvent => ({ id: 'e', calendar: 'iut', title: 'Point avec Sophie', start: '2026-11-21T14:00:00Z', end: '2026-11-21T15:00:00Z', location: null, attendees: [{ name: 'Sophie Laurent', address: 'Sophie@univ.fr' }, { name: 'David', address: 'david@iut.fr' }], ...over })

describe('avant un rendez-vous : échanges avec les participants (F-71)', () => {
  it('relie les mails reçus et envoyés aux participants, hors soi-même, du plus récent au plus ancien', () => {
    const mails = [
      mail('old', { at: '2026-11-01T10:00:00Z' }),
      mail('sent', { folder: 'sent', from: { name: 'David', address: 'david@iut.fr' }, to: [{ name: 'Sophie Laurent', address: 'sophie@univ.fr' }], at: '2026-11-20T16:00:00Z' }),
      mail('new', { at: '2026-11-21T08:00:00Z' }),
      mail('other', { from: { name: 'Marc', address: 'marc@x.fr' } }),
    ]
    const b = briefFor(ev(), mails, now, ['david@iut.fr'])
    expect(b.people).toEqual(['Sophie Laurent'])
    expect(b.items.map((i) => [i.mail.id, i.direction])).toEqual([['new', 'received'], ['sent', 'sent'], ['old', 'received']])
  })

  it('ignore les mails de plus de 90 jours, les boîtes partagées et la corbeille, et plafonne la liste', () => {
    const mails = [
      mail('ancient', { at: '2026-05-01T10:00:00Z' }), mail('shared', { mailbox: 'contact' }), mail('bin', { folder: 'trash' }),
      ...Array.from({ length: 6 }, (_, i) => mail(`m${i}`, { at: `2026-11-1${i}T10:00:00Z` })),
    ]
    const b = briefFor(ev(), mails, now, ['david@iut.fr'])
    expect(b.items).toHaveLength(3)
    expect(b.items.every((i) => i.mail.id.startsWith('m'))).toBe(true)
  })

  it('sans participants connus, se rabat sur le titre dans l’objet des mails', () => {
    const b = briefFor(ev({ attendees: [] }), [mail('a', { subject: 'Re : Point avec Sophie' }), mail('b', { subject: 'Autre sujet' })], now)
    expect(b.people).toEqual([])
    expect(b.items.map((i) => i.mail.id)).toEqual(['a'])
  })
})
