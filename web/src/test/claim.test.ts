import { describe, expect, it } from 'vitest'
import { collisionWith, handle, release, take, takeOver } from '../domain/claim'
import type { Mail } from '../domain/types'

const mail: Mail = {
  id: 'm', mailbox: 'contact', folder: 'inbox', from: { name: 'X', address: 'x@y.fr' }, to: [], subject: 's', paragraphs: [],
  at: '2026-11-20T08:00:00Z', unread: true, attachments: [], suivi: null, claim: { state: 'new', by: null }, composing: null,
  notes: [], activity: [], hints: {},
}
const AT = new Date('2026-11-20T09:00:00Z')

describe('boîte partagée', () => {
  it('prendre, libérer et traiter sont inscrits au journal', () => {
    const taken = take(mail, 'Claire', AT)
    expect(taken.claim).toEqual({ state: 'taken', by: 'Claire' })
    expect(release(taken, 'Claire', AT).claim?.state).toBe('new')
    expect(handle(taken, 'Claire', AT).claim).toEqual({ state: 'handled', by: 'Claire' })
    expect(handle(take(mail, 'Claire', AT), 'Claire', AT).activity.map((a) => a.action)).toEqual(['take', 'handled'])
  })
  it('avertit si un autre membre a pris la demande ou rédige (F-68)', () => {
    expect(collisionWith(take(mail, 'Claire', AT), 'Moi')).toBe('Claire')
    expect(collisionWith({ ...mail, composing: 'Marc' }, 'Moi')).toBe('Marc')
    expect(collisionWith(take(mail, 'Moi', AT), 'Moi')).toBeNull()
    expect(collisionWith(mail, 'Moi')).toBeNull()
  })
  it('reprendre la demande informe l’ancien responsable', () => {
    const out = takeOver(take(mail, 'Claire', AT), 'Moi', AT)
    expect(out.claim?.by).toBe('Moi')
    expect(out.activity.at(-1)?.action).toBe('takeOver:Claire')
  })
})
