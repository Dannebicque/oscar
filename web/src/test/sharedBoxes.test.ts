import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import axe from 'axe-core'
import { api } from '../api'
import SharedMailboxesPanel from '../components/settings/SharedMailboxesPanel.vue'
import { useAccountsStore } from '../stores/accounts'
import { mountApp } from './helpers'

afterEach(() => { vi.restoreAllMocks(); document.body.innerHTML = '' })

async function panel() {
  const ctx = await mountApp(SharedMailboxesPanel)
  await useAccountsStore(ctx.pinia).load()
  await flushPromises()
  return ctx
}
const dialog = () => document.body.querySelector('[role="dialog"]') as HTMLElement
const inDialog = (label: string) => [...dialog().querySelectorAll('button')].find((b) => b.textContent?.includes(label)) as HTMLElement
const button = (root: HTMLElement, label: string) => [...root.querySelectorAll('button')].find((b) => b.textContent?.includes(label) || b.getAttribute('aria-label') === label) as HTMLElement

describe('F-40 : gérer les boîtes partagées', () => {
  it('crée une boîte, y ajoute un collègue et dit ce qui est partagé', async () => {
    await panel()
    button(document.body, 'Nouvelle boîte partagée').click()
    await flushPromises()
    expect(dialog().textContent).toContain('Leurs mails personnels restent privés')
    const set = async (id: string, v: string) => { const el = document.getElementById(id) as HTMLInputElement; el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); await flushPromises() }
    await set('box-address', 'secretariat@iut-troyes.fr')
    await set('box-name', 'Secrétariat')
    document.getElementById('box-form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flushPromises()
    const box = () => document.body.querySelector('[data-box="secretariat@iut-troyes.fr"]') as HTMLElement
    expect(box().textContent).toContain('Administrateur')

    const who = box().querySelector('input[id^="who-"]') as HTMLInputElement
    who.value = 'claire'; who.dispatchEvent(new Event('input', { bubbles: true })); await flushPromises()
    box().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    await flushPromises()
    expect(box().textContent).toContain('Claire')
  })

  it('retirer quelqu’un demande confirmation en le nommant ; le dernier administrateur ne peut pas partir', async () => {
    await panel()
    const box = () => document.body.querySelector('[data-box^="contact@"]') as HTMLElement
    const remove = vi.spyOn(api.accounts.shared, 'removeMember')
    button(box(), 'Retirer Claire').click()
    await flushPromises()
    expect(dialog().textContent).toContain('Retirer Claire de contact@')
    expect(remove).not.toHaveBeenCalled()
    inDialog('Retirer Claire').click()
    await flushPromises()
    expect(box().querySelector('.members')!.textContent).not.toContain('Claire')

    button(box(), 'Quitter la boîte').click()
    await flushPromises()
    inDialog('Quitter la boîte').click()
    await flushPromises()
    expect(box()).not.toBeNull() // seul administrateur : refusé, la boîte reste
  })

  it('reste accessible', async () => {
    await panel()
    const r = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } })
    expect(r.violations.map((v) => v.id)).toEqual([])
  })
})

describe('boutons', () => {
  it('un bouton ordinaire n’est pas annoncé comme une bascule', async () => {
    await panel()
    const add = button(document.body, 'Nouvelle boîte partagée')
    expect([add.getAttribute('aria-pressed'), add.getAttribute('aria-expanded')]).toEqual([null, null])
  })
})
