export default {
  app: { name: 'Oscar', tagline: 'Votre majordome numérique.' },
  nav: { label: 'Navigation principale', suivis: 'Suivis', settings: 'Réglages' },
  suivis: {
    title: 'Mes suivis',
    actionCount: 'Aucun suivi à traiter | 1 suivi à traiter | {n} suivis à traiter',
    empty: 'Plus rien ne réclame ton attention, le plateau est à toi.',
    loading: 'Chargement des suivis…',
    close: 'Clore',
    closeLabel: 'Clore le suivi « {subject} »',
    due: 'Échéance {date}',
    aiBadge: "Suggéré par l'IA",
  },
  state: {
    toHandle: 'À traiter',
    relanceDue: 'Relance due',
    late: 'En retard',
    waiting: 'En attente',
    done: 'Terminé',
  },
  settings: {
    title: 'Réglages',
    language: 'Langue',
    languageHelp: "La langue de l'interface. Le ton d'Oscar est relu pour chaque langue.",
  },
}
