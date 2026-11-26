import { describe, expect, it, vi } from 'vitest'
import { listAll, PAGE_SIZE } from '../api/http'

const rows = (from: number, n: number) => Array.from({ length: n }, (_, i) => ({ id: String(from + i) }))
const server = (pages: { id: string }[][]) => vi.fn(async (_method: string, path: string) => pages[Number(path.split('page=')[1] ?? 1) - 1] ?? [])

describe('listes de l’API : toutes les pages sont lues (F-10, F-59, F-31, F-71)', () => {
  it('enchaîne les pages jusqu’à la première page incomplète', async () => {
    const req = server([rows(0, PAGE_SIZE), rows(PAGE_SIZE, PAGE_SIZE), rows(2 * PAGE_SIZE, 3)])
    const all = await listAll(req as never, '/api/tasks')
    expect(all).toHaveLength(2 * PAGE_SIZE + 3)
    expect(req.mock.calls.map((c) => c[1])).toEqual(['/api/tasks', '/api/tasks?page=2', '/api/tasks?page=3'])
  })
  it('une seule requête quand tout tient dans une page', async () => {
    const req = server([rows(0, 35)])
    expect(await listAll(req as never, '/api/tasks')).toHaveLength(35)
    expect(req).toHaveBeenCalledTimes(1)
  })
  it('ne tourne pas en rond si le serveur ignore le numéro de page', async () => {
    const req = vi.fn(async () => rows(0, PAGE_SIZE))
    expect(await listAll(req as never, '/api/tasks')).toHaveLength(PAGE_SIZE)
    expect(req).toHaveBeenCalledTimes(2)
  })
})
