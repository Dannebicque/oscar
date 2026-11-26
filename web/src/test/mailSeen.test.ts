import { afterEach, describe, expect, it, vi } from 'vitest'
import { createHttpApi } from '../api/http'

const raw = (id: string, folder: string, seen: boolean) => ({ id, folder, seen, messageId: `<${id}@x>`, fromName: 'Sophie', fromAddress: 's@x.fr', toAddresses: [], subject: 'Planning', date: '2026-11-19T09:00:00+00:00' })
afterEach(() => vi.unstubAllGlobals())

describe('lu / non lu avec la vraie API (F-16, F-72)', () => {
  it('un mail non lu sur le serveur est non lu à l’écran, jamais un message envoyé', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify([raw('a', 'INBOX', false), raw('b', 'INBOX', true), raw('c', 'Sent', false)]), { status: 200 })))
    const list = await createHttpApi('http://api.test').mails.list()
    expect(list.map((m) => m.unread)).toEqual([true, false, false])
  })

  it('ouvrir un mail le dit au serveur', async () => {
    const fetcher = vi.fn(async (_url: string, _init?: RequestInit) => new Response('{}', { status: 200 }))
    vi.stubGlobal('fetch', fetcher)
    await createHttpApi('http://api.test').mails.markRead('a')
    const [url, init] = fetcher.mock.calls[0]!
    expect(url).toBe('http://api.test/api/mails/a/seen')
    expect([init?.method, init?.body]).toEqual(['POST', '{"seen":true}'])
  })
})
