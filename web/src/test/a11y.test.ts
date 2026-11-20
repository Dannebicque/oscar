import axe from 'axe-core'
import { afterEach, describe, expect, it } from 'vitest'
import AgendaView from '../views/AgendaView.vue'
import HomeView from '../views/HomeView.vue'
import JournalView from '../views/JournalView.vue'
import MailView from '../views/MailView.vue'
import NotificationsView from '../views/NotificationsView.vue'
import RecapView from '../views/RecapView.vue'
import SettingsView from '../views/SettingsView.vue'
import SuivisView from '../views/SuivisView.vue'
import TasksView from '../views/TasksView.vue'
import ComposeView from '../views/ComposeView.vue'
import AppShell from '../components/layout/AppShell.vue'
import { mountApp } from './helpers'

/** Audit axe-core sur chaque écran (WCAG 2.2 AA, N-01). Le contraste se vérifie dans un vrai navigateur (jsdom ne calcule pas les couleurs). */
async function audit() {
  const res = await axe.run(document.body, { rules: { 'color-contrast': { enabled: false } }, runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] })
  return res.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)
}

const SCREENS: [string, unknown, string?, Record<string, unknown>?][] = [
  ['accueil', HomeView], ['suivis', SuivisView], ['mails', MailView], ['mail d’un alias', MailView, '/mails/c1', { id: 'c1' }],
  ['mail avec suivi', MailView, '/mails/m1', { id: 'm1' }], ['agenda', AgendaView], ['tâches', TasksView], ['écrire', ComposeView],
  ['récap', RecapView], ['alertes', NotificationsView], ['journal', JournalView], ['réglages', SettingsView],
]

describe('accessibilité (axe-core)', () => {
  afterEach(() => { document.body.innerHTML = '' })
  for (const [name, comp, path, props] of SCREENS) {
    it(`${name} : aucune violation`, async () => {
      await mountApp(comp as never, path ?? '/', props ?? {})
      expect(await audit()).toEqual([])
    })
  }
  it('le shell expose navigation, aria-current et liens d’évitement', async () => {
    const { wrapper } = await mountApp(AppShell as never, '/', {}, { host: false })
    expect(wrapper.find('nav[aria-label]').exists()).toBe(true)
    expect(wrapper.findAll('a.skip')).toHaveLength(2)
    expect(wrapper.find('a[aria-current="page"]').exists()).toBe(true)
    expect(await audit()).toEqual([])
  })
})
