import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../api'
import { reportFailure } from '../errors'
import { bareId, plainSubject, sameId } from '../domain/mailIds'
import { useUiStore } from '../stores/ui'
import { mountApp } from './helpers'

describe('une action qui échoue le dit (F-79)', () => {
  afterEach(() => vi.restoreAllMocks())

  it('sans code technique, une seule fois, et le message de l’API pour un refus', async () => {
    await mountApp({ template: '<div/>' })
    vi.spyOn(console, 'error').mockImplementation(() => undefined)
    const ui = useUiStore()
    reportFailure(new ApiError(500, 'An error occurred'))
    reportFailure(new Error('TypeError: x is undefined'))
    reportFailure(new ApiError(409, 'Claire s’en occupe déjà.'))
    reportFailure(new ApiError(401, 'expiré'))
    expect(ui.toasts.map((t) => [t.tone, t.message])).toEqual([['error', ui.toasts[0].message], ['error', 'Claire s’en occupe déjà.']])
    expect(ui.toasts[0].message).not.toContain('error')
  })

  it('compare les Message-ID et nettoie les objets d’une seule façon', () => {
    expect(bareId(' <A@x.fr> ')).toBe('a@x.fr')
    expect(sameId('<a@x>', 'A@X')).toBe(true)
    expect(sameId(null, null)).toBe(false)
    expect(plainSubject('Re : Devis')).toBe('Devis')
  })
})
