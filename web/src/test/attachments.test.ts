import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api'
import AttachmentDropzone from '../components/mail/AttachmentDropzone.vue'
import { addFiles, limitBytes, MAX_FILES, sizeLabel } from '../domain/attachments'
import { useAccountsStore } from '../stores/accounts'
import ComposeView from '../views/ComposeView.vue'
import { addRecipient, mountApp } from './helpers'

afterEach(() => { vi.restoreAllMocks(); document.body.innerHTML = '' })

const MB = 1024 * 1024
const file = (name: string, size: number, lastModified = 1) => {
  const f = new File(['x'], name, { lastModified })
  Object.defineProperty(f, 'size', { value: size })
  return f
}

describe('pièces jointes : règles', () => {
  it('limite par défaut de 10 Mo, libellés lisibles', () => {
    expect(limitBytes(null)).toBe(10 * MB)
    expect(limitBytes(25)).toBe(25 * MB)
    expect([sizeLabel(500), sizeLabel(2048), sizeLabel(1.5 * MB), sizeLabel(12 * MB)]).toEqual(['500 o', '2 Ko', '1.5 Mo', '12 Mo'])
  })

  it('ajoute dans la limite, ignore les doublons, refuse le trop gros, le vide et le trop nombreux', () => {
    const r = addFiles([], [file('a.pdf', 4 * MB), file('a.pdf', 4 * MB), file('b.zip', 7 * MB), file('c.txt', 2 * MB), file('vide.txt', 0)], 10 * MB)
    expect(r.files.map((f) => f.name)).toEqual(['a.pdf', 'c.txt'])
    expect(r.refused).toEqual([{ name: 'b.zip', reason: 'size' }, { name: 'vide.txt', reason: 'empty' }])
    const many = addFiles([], Array.from({ length: MAX_FILES + 2 }, (_, i) => file(`f${i}`, 1000, i)), 10 * MB)
    expect(many.files).toHaveLength(MAX_FILES)
    expect(many.refused.every((x) => x.reason === 'count')).toBe(true)
  })
})

describe('zone de dépôt', () => {
  const mountZone = async (limitMb: number | null = null) => {
    const { wrapper } = await mountApp(AttachmentDropzone, '/ecrire', { modelValue: [], limitMb })
    return wrapper
  }
  const dropOnDocument = (files: File[], types = ['Files']) => {
    const ev = new Event('drop', { bubbles: true, cancelable: true }) as Event & { dataTransfer: unknown }
    ev.dataTransfer = { types, files }
    document.dispatchEvent(ev)
    return ev
  }

  it('affiche la limite en permanence, accepte un dépôt et montre la place utilisée', async () => {
    const wrapper = await mountZone(20)
    expect(wrapper.text()).toContain('20 Mo')
    expect(wrapper.text()).toContain('Glisse tes fichiers ici')
    const ev = dropOnDocument([file('rapport.pdf', 3 * MB)])
    await flushPromises()
    expect(ev.defaultPrevented).toBe(true)
    const files = wrapper.emitted('update:modelValue')!.at(-1)![0] as File[]
    expect(files.map((f) => f.name)).toEqual(['rapport.pdf'])
  })

  it('un glisser qui n’est pas un fichier (du texte) n’est pas intercepté', async () => {
    await mountZone()
    const ev = dropOnDocument([], ['text/plain'])
    expect(ev.defaultPrevented).toBe(false)
  })

  it('le bouton Parcourir accepte plusieurs fichiers d’un coup et refuse ce qui dépasse, en le disant', async () => {
    const { wrapper } = await mountApp(AttachmentDropzone, '/ecrire', { modelValue: [], limitMb: 5 })
    const input = wrapper.find('input[type="file"]')
    expect(input.attributes('multiple')).toBeDefined()
    Object.defineProperty(input.element, 'files', { value: [file('a.pdf', 2 * MB), file('b.pdf', 2 * MB), file('c.zip', 3 * MB)], configurable: true })
    await input.trigger('change')
    await flushPromises()
    const files = wrapper.emitted('update:modelValue')!.at(-1)![0] as File[]
    expect(files.map((f) => f.name)).toEqual(['a.pdf', 'b.pdf'])
    expect(wrapper.text()).toContain('« c.zip » dépasse la limite')
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  })
})

describe('dans l’éditeur', () => {
  it('l’ancien bouton « Joindre un fichier » a disparu au profit de la zone, et la limite suit le compte', async () => {
    const { wrapper, pinia } = await mountApp(ComposeView, '/ecrire')
    const accounts = useAccountsStore(pinia)
    await accounts.load()
    accounts.mail[0].attachmentLimitMb = 25
    await flushPromises()
    expect(wrapper.text()).not.toContain('Joindre un fichier')
    expect(wrapper.text()).toContain('Glisse tes fichiers ici')
    expect(wrapper.text()).toContain('25 Mo')
  })

  it('un fichier déposé part avec le message', async () => {
    const { wrapper } = await mountApp(ComposeView, '/ecrire')
    await flushPromises()
    const send = vi.spyOn(api.mails, 'send')
    const ev = new Event('drop', { bubbles: true, cancelable: true }) as Event & { dataTransfer: unknown }
    ev.dataTransfer = { types: ['Files'], files: [new File(['bonjour'], 'note.txt', { type: 'text/plain' })] }
    document.dispatchEvent(ev)
    await flushPromises()
    expect(wrapper.text()).toContain('note.txt')
    await addRecipient(wrapper, 'to', 'Marc <marc@x.fr>')
    await wrapper.find('#subject').setValue('Avec pièce jointe')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    await vi.waitFor(() => expect(send).toHaveBeenCalled())
    expect(send.mock.calls[0][0].attachments?.[0]).toMatchObject({ name: 'note.txt', type: 'text/plain' })
  })
})
