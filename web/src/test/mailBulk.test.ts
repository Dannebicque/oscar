import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { useMailsStore } from '../stores/mails'
import { useSettingsStore } from '../stores/settings'
import { useUiStore } from '../stores/ui'
import MailView from '../views/MailView.vue'
import { mountApp } from './helpers'

afterEach(() => { document.body.innerHTML = '' })

async function open() {
  const ctx = await mountApp(MailView, '/mails')
  await flushPromises()
  await ctx.wrapper.find('.sel-toggle').trigger('click')
  return ctx
}
const click = (label: string) => [...document.body.querySelectorAll('button')].find((b) => b.getAttribute('aria-label') === label || b.textContent?.includes(label))!.click()

describe('traitement par lot des mails', () => {
  it('sélectionner puis archiver déplace tous les messages cochés, avec une annulation unique', async () => {
    const { wrapper, pinia } = await open()
    const boxes = wrapper.findAll('li[data-mail] input[type="checkbox"]')
    expect(boxes.length).toBeGreaterThan(2)
    await boxes[0].setValue(true)
    await boxes[1].setValue(true)
    expect(wrapper.find('.bulk-n').text()).toContain('2 sélectionnés')
    const ids = wrapper.findAll('li[data-mail]').slice(0, 2).map((l) => l.attributes('data-mail')!)
    await wrapper.find('.bulk-acts button[aria-label="Archiver"]').trigger('click')
    await flushPromises()
    const mails = useMailsStore(pinia)
    expect(ids.map((i) => mails.byId(i)?.folder)).toEqual(['archive', 'archive'])
    const toast = useUiStore(pinia).toasts.at(-1)!
    expect(toast.message).toContain('2 messages déplacés')
    await toast.undo!()
    expect(ids.map((i) => mails.byId(i)?.folder)).toEqual(['inbox', 'inbox'])
  })

  it('la corbeille d’un lot demande confirmation, et annuler la confirmation ne déplace rien', async () => {
    const { wrapper, pinia } = await open()
    await wrapper.find('.bulk-all input').setValue(true)
    const before = useMailsStore(pinia).items.filter((m) => m.folder === 'trash').length
    await wrapper.find('.bulk-acts button[aria-label="Supprimer"]').trigger('click')
    await flushPromises()
    expect(document.body.textContent).toContain('messages à la corbeille ?')
    click('Annuler')
    await flushPromises()
    expect(useMailsStore(pinia).items.filter((m) => m.folder === 'trash').length).toBe(before)
    await wrapper.find('.bulk-acts button[aria-label="Supprimer"]').trigger('click')
    await flushPromises()
    click('Mettre à la corbeille')
    await flushPromises()
    expect(useMailsStore(pinia).items.filter((m) => m.folder === 'trash').length).toBeGreaterThan(before)
  })

  it('sans confirmation réglée, la corbeille d’un lot part directement', async () => {
    const { wrapper, pinia } = await open()
    useSettingsStore(pinia).s.confirmDelete = false
    await wrapper.find('li[data-mail] input[type="checkbox"]').setValue(true)
    await wrapper.find('.bulk-acts button[aria-label="Supprimer"]').trigger('click')
    await flushPromises()
    expect(document.body.textContent).not.toContain('à la corbeille ?')
    expect(useMailsStore(pinia).items.some((m) => m.folder === 'trash')).toBe(true)
  })
})
