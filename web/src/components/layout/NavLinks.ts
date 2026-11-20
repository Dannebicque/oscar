export interface NavItem {
  name: string
  to: string
  icon: string
  label: string
}

/** Cinq destinations de la barre du bas (U-01), Suivis au centre ; Alertes et Réglages s'y ajoutent sur grand écran. */
export const MAIN_NAV: NavItem[] = [
  { name: 'home', to: '/', icon: 'home', label: 'nav.home' },
  { name: 'mails', to: '/mails', icon: 'mail', label: 'nav.mails' },
  { name: 'suivis', to: '/suivis', icon: 'follow', label: 'nav.suivis' },
  { name: 'agenda', to: '/agenda', icon: 'calendar', label: 'nav.agenda' },
  { name: 'tasks', to: '/taches', icon: 'task', label: 'nav.tasks' },
]
