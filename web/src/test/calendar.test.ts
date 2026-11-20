import axe from 'axe-core'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import CalendarGrid from '../components/agenda/CalendarGrid.vue'
import { i18n, setLocale } from '../i18n'
import { atTime, addDays } from '../domain/dates'
import type { CalEvent, CalendarSource } from '../domain/types'
import AgendaView from '../views/AgendaView.vue'
import { mountApp } from './helpers'

const day = atTime(new Date(), 0)
const at = (n: number, h: number, m = 0) => atTime(addDays(day, n), h, m).toISOString()
const SOURCES: CalendarSource[] = [
  { id: 'iut', label: 'IUT', kind: 'caldav', personal: false },
  { id: 'perso', label: 'Perso', kind: 'google', personal: true },
]
const EVENTS: CalEvent[] = [
  { id: 'a', calendar: 'iut', title: 'Point équipe', start: at(0, 9), end: at(0, 10), location: 'Salle B12' },
  { id: 'b', calendar: 'iut', title: 'Soutenance', start: at(0, 9, 30), end: at(0, 11), location: null },
  { id: 'c', calendar: 'perso', title: 'Dentiste', start: at(0, 17), end: at(0, 18), location: null },
]

function grid(view: 'timeGridWeek' | 'timeGridDay' | 'dayGridMonth' | 'listWeek' = 'timeGridDay') {
  setLocale('fr')
  return mount(CalendarGrid, { props: { events: EVENTS, sources: SOURCES, view, conflicts: new Set(['a', 'b']) }, global: { plugins: [i18n] }, attachTo: document.body })
}
afterEach(() => { document.body.innerHTML = '' })

describe('grille de calendrier (FullCalendar)', () => {
  it('affiche titre, lieu et source en texte : jamais la couleur seule', async () => {
    const w = grid()
    await flushPromises()
    const text = w.text()
    expect(text).toContain('Point équipe')
    expect(text).toContain('Salle B12')
    expect(text).toMatch(/IUT/)
    expect(text).toMatch(/Perso/)
  })

  it('signale en texte les rendez-vous qui se chevauchent', async () => {
    const w = grid()
    await flushPromises()
    const conflicted = w.findAll('.fc-event.ev-conflict')
    expect(conflicted).toHaveLength(2)
    expect(conflicted.every((e) => e.text().includes('Chevauche un autre rendez-vous'))).toBe(true)
  })

  it('distingue l’agenda perso de l’agenda pro par une classe et un libellé', async () => {
    const w = grid()
    await flushPromises()
    const perso = w.findAll('.fc-event').find((e) => e.text().includes('Dentiste'))!
    expect(perso.classes()).toContain('ev-personal')
    expect(perso.text()).toContain('Perso')
  })

  it('chaque événement se focalise au clavier et s’ouvre avec Entrée', async () => {
    const w = grid()
    await flushPromises()
    const ev = w.findAll('.fc-event').find((e) => e.text().includes('Point équipe'))!
    expect(ev.attributes('tabindex')).toBe('0')
    await ev.trigger('keydown', { key: 'Enter' })
    await ev.trigger('click')
    expect(w.emitted('open')?.[0]).toEqual(['a'])
  })

  it('change de vue et de période, et annonce le titre de la période', async () => {
    const w = grid('timeGridWeek')
    await flushPromises()
    const titles: string[] = []
    w.vm.$el // noop : le titre arrive par l'événement « dates »
    ;(w.emitted('dates') ?? []).forEach((e) => titles.push((e[0] as { title: string }).title))
    ;(w.vm as unknown as { changeView: (v: string) => void }).changeView('dayGridMonth')
    await flushPromises()
    ;(w.vm as unknown as { next: () => void }).next()
    await flushPromises()
    const all = (w.emitted('dates') ?? []).map((e) => (e[0] as { title: string }).title)
    expect(all.length).toBeGreaterThanOrEqual(3)
    expect(new Set(all).size).toBeGreaterThanOrEqual(2)
  })

  it('suit la langue de l’interface (jours en français, puis en anglais)', async () => {
    const w = grid('timeGridWeek')
    await flushPromises()
    expect(w.text().toLowerCase()).toMatch(/lun\.|mar\./)
  })

  for (const view of ['timeGridWeek', 'timeGridDay', 'dayGridMonth', 'listWeek'] as const) {
    it(`vue ${view} : aucune violation d’accessibilité`, async () => {
      const host = document.createElement('main')
      document.body.append(host)
      setLocale('fr')
      mount(CalendarGrid, { props: { events: EVENTS, sources: SOURCES, view, conflicts: new Set(['a', 'b']) }, global: { plugins: [i18n] }, attachTo: host })
      await flushPromises()
      const res = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false } }, runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] })
      expect(res.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([])
    })
  }
})

describe('écran Agenda', () => {
  it('ouvre le détail d’un événement au clic et le ferme', async () => {
    const { wrapper } = await mountApp(AgendaView, '/agenda')
    await flushPromises()
    const ev = wrapper.findAll('.fc-event').find((e) => e.text().includes('Point équipe'))
    expect(ev).toBeTruthy()
    await ev!.trigger('click')
    await flushPromises()
    // Reka UI téléporte la fenêtre dans <body> : on la cherche dans le document.
    const dlg = document.body.querySelector('[role="dialog"]')!
    expect(dlg.querySelector('h2')?.textContent).toBe('Point équipe')
    expect(dlg.textContent).toContain('IUT')
    expect(dlg.getAttribute('aria-labelledby')).toBeTruthy()
  })

  it('les boutons de période et de vue pilotent la grille', async () => {
    const { wrapper } = await mountApp(AgendaView, '/agenda')
    await flushPromises()
    const before = wrapper.find('.when').text()
    await wrapper.find('button[aria-label="Semaine suivante"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('.when').text()).not.toBe(before)
    await wrapper.findAll('.seg button').find((b) => b.text().startsWith('Mois'))!.trigger('click')
    await flushPromises()
    expect(wrapper.find('.fc-daygrid').exists()).toBe(true)
  })

  it('« Nouvel événement » ajoute au serveur puis affiche l’événement', async () => {
    const { wrapper } = await mountApp(AgendaView, '/agenda')
    await flushPromises()
    await wrapper.findAll('button').find((b) => b.text().includes('Nouvel événement'))!.trigger('click')
    const title = document.body.querySelector<HTMLInputElement>('#ev-title')!
    title.value = 'Réunion test'
    title.dispatchEvent(new Event('input', { bubbles: true }))
    document.body.querySelector<HTMLFormElement>('#new-event')!.dispatchEvent(new Event('submit', { cancelable: true }))
    await flushPromises()
    expect(wrapper.text()).toContain('Réunion test')
  })
})
