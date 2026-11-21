import { flushPromises } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import MailList from '../components/mail/MailList.vue'
import { emptyCriteria, matches, parseQuery } from '../domain/mailSearch'
import type { Mail } from '../domain/types'
import { useMailsStore } from '../stores/mails'
import { mountApp } from './helpers'

const mail = (over: Partial<Mail>): Mail => ({
  id: 'x', mailbox: 'personal', folder: 'inbox', from: { name: 'Claire Petit', address: 'claire@iut.fr' }, to: [], subject: 'Devis des affiches', at: '2026-11-10T10:00:00Z', unread: false,
  paragraphs: ['Bonjour, voici le devis.'], attachments: [], suivi: null, hints: {}, ...over,
} as Mail)

describe('recherche avancée (F-12)', () => {
  it('les opérateurs tapés dans la recherche deviennent des critères, le reste est du texte libre', () => {
    const c = parseQuery('de:claire objet:"devis des" apres:2026-11-01 avant:2026-11-30 pj suivi nonlu affiches')
    expect(c).toMatchObject({ from: 'claire', subject: 'devis des', after: '2026-11-01', before: '2026-11-30', attachment: true, followed: true, unread: true, text: 'affiches' })
    expect(parseQuery('from:marc subject:budget').from).toBe('marc')
    expect(parseQuery('apres:demain').after).toBe('')
  })

  it('chaque critère filtre, et ils se combinent', () => {
    const m = mail({ attachments: [{ name: 'a.pdf', size: 1, type: 'application/pdf' } as never], suivi: 's1', unread: true })
    expect(matches(m, { ...emptyCriteria(), from: 'CLAIRE' })).toBe(true)
    expect(matches(m, { ...emptyCriteria(), from: 'marc' })).toBe(false)
    expect(matches(m, { ...emptyCriteria(), after: '2026-11-11' })).toBe(false)
    expect(matches(m, { ...emptyCriteria(), before: '2026-11-10', after: '2026-11-10' })).toBe(true)
    expect(matches(m, { ...emptyCriteria(), attachment: true, followed: true, unread: true })).toBe(true)
    expect(matches(mail({}), { ...emptyCriteria(), attachment: true })).toBe(false)
    expect(matches(mail({}), { ...emptyCriteria(), text: 'voici' })).toBe(true)
  })

  it('le panneau de filtres de la liste réduit les messages affichés', async () => {
    const { wrapper, pinia } = await mountApp(MailList, '/mails', { mails: [], selectedId: null, shared: false })
    await useMailsStore(pinia).load()
    await wrapper.setProps({ mails: useMailsStore(pinia).items.filter((x) => x.folder === 'inbox') })
    const all = wrapper.findAll('li[data-mail]').length
    expect(all).toBeGreaterThan(1)
    await wrapper.find('.adv-toggle').trigger('click')
    expect(wrapper.find('.adv-toggle').attributes('aria-expanded')).toBe('true')
    await wrapper.find('#mail-q').setValue('de:claire')
    await flushPromises()
    const some = wrapper.findAll('li[data-mail]').length
    expect(some).toBeLessThan(all)
    await wrapper.find('.adv-reset').trigger('click')
    expect(wrapper.findAll('li[data-mail]').length).toBe(all)
  })
})
