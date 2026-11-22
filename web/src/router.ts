import { nextTick } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { i18n } from './i18n'
import { apiMode, session } from './api'
import { useAuthStore } from './stores/auth'
import { useInstanceStore } from './stores/instance'
import { useUiStore } from './stores/ui'

/** Chaque route : clé de titre i18n. Les vues sont chargées à la demande. */
export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/installation', name: 'setup', component: () => import('./views/SetupView.vue'), meta: { title: 'setup.title', public: true } },
    { path: '/connexion', name: 'login', component: () => import('./views/LoginView.vue'), meta: { title: 'login.title', public: true } },
    { path: '/inscription', name: 'register', component: () => import('./views/RegisterView.vue'), meta: { title: 'auth.registerTitle', public: true } },
    { path: '/mot-de-passe-oublie', name: 'forgot', component: () => import('./views/ForgotView.vue'), meta: { title: 'auth.forgotTitle', public: true } },
    { path: '/mot-de-passe/reinitialiser', name: 'reset', component: () => import('./views/ResetView.vue'), meta: { title: 'auth.resetTitle', public: true } },
    { path: '/bienvenue', name: 'welcome', component: () => import('./views/WelcomeView.vue'), meta: { title: 'wizard.title', standalone: true } },
    { path: '/', name: 'home', component: () => import('./views/HomeView.vue'), meta: { title: 'nav.home' } },
    { path: '/mails/:id?', name: 'mails', component: () => import('./views/MailView.vue'), meta: { title: 'nav.mails', bleed: true }, props: true },
    { path: '/suivis', name: 'suivis', component: () => import('./views/SuivisView.vue'), meta: { title: 'nav.suivis' } },
    { path: '/agenda', name: 'agenda', component: () => import('./views/AgendaView.vue'), meta: { title: 'nav.agenda' } },
    { path: '/taches', name: 'tasks', component: () => import('./views/TasksView.vue'), meta: { title: 'nav.tasks' } },
    { path: '/ecrire', name: 'compose', component: () => import('./views/ComposeView.vue'), meta: { title: 'compose.title' } },
    { path: '/recap', name: 'recap', component: () => import('./views/RecapView.vue'), meta: { title: 'recap.title' } },
    { path: '/alertes', name: 'alerts', component: () => import('./views/NotificationsView.vue'), meta: { title: 'nav.alerts' } },
    { path: '/reglages', name: 'settings', component: () => import('./views/SettingsView.vue'), meta: { title: 'nav.settings' } },
    { path: '/administration', name: 'admin', component: () => import('./views/AdminView.vue'), meta: { title: 'admin.title', admin: true } },
    { path: '/journal', name: 'journal', component: () => import('./views/JournalView.vue'), meta: { title: 'journal.title' } },
    { path: '/:rest(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

/** Toute page sauf la connexion demande une session ; on y revient après s'être connecté. */
router.beforeEach(async (to) => {
  // Instance neuve (aucun administrateur) : tout mène à l'assistant d'installation ; une fois installée, il n'est plus accessible.
  const instance = useInstanceStore()
  if (apiMode === 'http' && !instance.loaded) await instance.load()
  if (instance.setupNeeded && to.name !== 'setup') return { name: 'setup' }
  if (!instance.setupNeeded && to.name === 'setup') return { name: 'login' }
  const auth = useAuthStore()
  if (!auth.checked) await auth.restore()
  if (to.meta.admin && !session.admin) return { name: 'home' } // réservé à l'administrateur de l'instance
  if (!to.meta.public && !auth.authenticated) return { name: 'login', query: to.fullPath === '/' ? {} : { next: to.fullPath } }
  if (to.name === 'login' && auth.authenticated && !to.hash) return { name: 'home' }
  return true
})

/** Titre de page et focus sur <h1> à chaque navigation : l'annonce se fait au lecteur d'écran (WCAG 2.4.2, 2.4.3). */
router.afterEach(async (to, from) => {
  const title = i18n.global.t(String(to.meta.title ?? 'app.name'))
  document.title = `${title} · ${useInstanceStore().name}`
  if (to.name === from.name && from.name !== undefined) return
  await nextTick()
  const h1 = document.querySelector<HTMLElement>('main h1')
  if (h1) {
    h1.tabIndex = -1
    h1.focus({ preventScroll: true })
  }
  useUiStore().announce(title)
})
