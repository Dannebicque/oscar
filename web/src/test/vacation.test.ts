import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import MailReader from '../components/mail/MailReader.vue'
import { isoDay } from '../domain/dates'
import { clock } from '../domain/clock'
import { useMailsStore } from '../stores/mails'
import { useSettingsStore } from '../stores/settings'
import { useSuivisStore } from '../stores/suivis'
import ComposeView from '../views/ComposeView.vue'
import { mountApp } from './helpers'

afterEach(() => { document.body.innerHTML = '' })
const day = (n: number) => { const d = clock.now(); d.setDate(d.getDate() + n); return isoDay(d) }

describe('mode congés', () => {
  it('actif entre les deux jours inclus, prévu avant, arrêtable', async () => {
    const { pinia } = await mountApp({ template: '<div/>' })
    const st = useSettingsStore(pinia)
    st.s.vacationFrom = day(-1); st.s.vacationTo = day(3)
    expect(st.vacationActive(clock.now())).toBe(true)
    st.s.vacationFrom = day(2); st.s.vacationTo = day(5)
    expect(st.vacationActive(clock.now())).toBe(false)
    expect(st.vacationPlanned(clock.now())).toBe(true)
    st.s.vacationFrom = day(-5); st.s.vacationTo = day(0)
    expect(st.vacationActive(clock.now())).toBe(true)
    st.stopVacation()
    expect(st.s.vacationFrom + st.s.vacationTo).toBe('')
  })

  it('aucune relance n’est proposée pendant les congés, elles reviennent ensuite ; rien n’est fermé', async () => {
    const { pinia } = await mountApp({ template: '<div/>' })
    const suivis = useSuivisStore(pinia)
    await suivis.load()
    const before = suivis.relances.length
    expect(before).toBeGreaterThan(0)
    const st = useSettingsStore(pinia)
    st.s.vacationFrom = day(-1); st.s.vacationTo = day(3)
    expect(suivis.relances).toHaveLength(0)
    expect(suivis.items.filter((s) => s.status === 'done').length).toBe(suivis.items.filter((s) => s.status === 'done').length)
    st.stopVacation()
    expect(suivis.relances).toHaveLength(before)
  })

  it('pendant les congés, un mail reçu propose le message d’absence, qui ouvre l’éditeur prérempli sans rien envoyer', async () => {
    const first = await mountApp({ template: '<div/>' })
    const mails = useMailsStore(first.pinia)
    await mails.load()
    const st = useSettingsStore(first.pinia)
    st.s.vacationFrom = day(-1); st.s.vacationTo = day(3); st.s.vacationMessage = 'Absent jusqu’à lundi.'
    await flushPromises()
    const m = mails.items.find((x) => x.folder === 'inbox' && x.mailbox === 'personal')!
    const { wrapper, router } = await mountApp(MailReader, `/mails/${m.id}`, { mail: { ...m } })
    await flushPromises()
    expect(wrapper.text()).toContain('Tu es en congés')
    ;[...wrapper.findAll('button')].find((b) => b.text().includes('Préparer le message d’absence'))!.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('compose')
    expect(router.currentRoute.value.query).toMatchObject({ reply: m.id, absence: '1' })
  })

  it('l’éditeur reprend le message d’absence, sans suivi précoché', async () => {
    const first = await mountApp({ template: '<div/>' })
    const mails = useMailsStore(first.pinia)
    await mails.load()
    const st = useSettingsStore(first.pinia)
    st.s.vacationFrom = day(-1); st.s.vacationTo = day(3); st.s.vacationMessage = 'Absent jusqu’à lundi.'
    await flushPromises()
    const m = mails.items.find((x) => x.folder === 'inbox' && x.mailbox === 'personal')!
    const { wrapper } = await mountApp(ComposeView, `/ecrire?reply=${m.id}&absence=1`)
    await flushPromises()
    expect((wrapper.find('#body').element as HTMLTextAreaElement).value).toContain('Absent jusqu’à lundi.')
    const follow = wrapper.findAll('[role="checkbox"]').find((c) => c.element.parentElement?.textContent?.includes('Suivre la réponse'))!
    expect(follow.attributes('aria-checked')).toBe('false')
  })
})
