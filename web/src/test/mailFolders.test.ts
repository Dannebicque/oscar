import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { folderTree, isCustom, pathOf, prettyPath } from '../domain/folders'
import { useMailsStore } from '../stores/mails'
import MailView from '../views/MailView.vue'
import { mountApp } from './helpers'

afterEach(() => { document.body.innerHTML = '' })

describe('dossiers et sous-dossiers (F-16)', () => {
  it('l’arborescence met les parents avant les enfants, avec leur niveau', () => {
    const rows = folderTree([
      { path: 'Projets/Jury', name: 'Jury', delimiter: '/', system: false, count: 0 }, { path: 'INBOX', name: 'INBOX', delimiter: '/', system: true, count: 5 },
      { path: 'Projets', name: 'Projets', delimiter: '/', system: false, count: 0 }, { path: 'Scolarité', name: 'Scolarité', delimiter: '/', system: false, count: 2 },
    ])
    expect(rows.map((r) => [r.path, r.depth])).toEqual([['Projets', 0], ['Projets/Jury', 1], ['Scolarité', 0]])
    expect(isCustom(rows[0].target) && pathOf(rows[1].target)).toBe('Projets/Jury')
    expect(prettyPath('Projets/Jury')).toBe('Projets › Jury')
  })

  it('le rail montre les dossiers du serveur, imbriqués ; on en crée un sous un parent', async () => {
    const { wrapper, pinia } = await mountApp(MailView, '/mails')
    await flushPromises()
    const names = wrapper.findAll('.ftree .fbtn').map((b) => b.attributes('title'))
    expect(names).toEqual(['Projets', 'Projets › Jury de janvier', 'Scolarité'])
    await wrapper.find('.fadd').trigger('click')
    await flushPromises()
    const input = document.body.querySelector('#folder-name') as HTMLInputElement
    input.value = 'Stages'
    input.dispatchEvent(new Event('input', { bubbles: true }))
    ;[...document.body.querySelectorAll('button')].find((b) => b.textContent?.includes('Créer le dossier'))!.click()
    await flushPromises()
    expect(useMailsStore(pinia).folders.map((f) => f.path)).toContain('Stages')
  })

  it('déplacer un mail vers un dossier personnel le range là, avec annulation ; un dossier plein ne se supprime pas', async () => {
    const { wrapper, pinia } = await mountApp(MailView, '/mails')
    await flushPromises()
    const mails = useMailsStore(pinia)
    const undo = await mails.moveTo('m1', 'f:Projets/Jury de janvier')
    expect(mails.byId('m1')!.folder).toBe('f:Projets/Jury de janvier')
    await expect(mails.removeFolder('Projets/Jury de janvier')).rejects.toThrow('pas vide')
    await mails.renameFolder('Projets', 'Jurys')
    expect(mails.byId('m1')!.folder).toBe('f:Jurys/Jury de janvier')
    await undo!()
    expect(mails.byId('m1')!.folder).toBe('inbox')
    await mails.removeFolder('Jurys/Jury de janvier')
    expect(mails.folders.map((f) => f.path)).not.toContain('Jurys/Jury de janvier')
    void wrapper
  })
})
