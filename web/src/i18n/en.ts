export default {
  app: { name: 'Oscar', tagline: 'Your digital butler.' },
  nav: { label: 'Main navigation', suivis: 'Follow-ups', settings: 'Settings' },
  suivis: {
    title: 'My follow-ups',
    actionCount: 'Nothing to handle | 1 follow-up to handle | {n} follow-ups to handle',
    empty: 'Nothing needs your attention. The floor is yours.',
    loading: 'Loading follow-ups…',
    close: 'Close',
    closeLabel: 'Close the follow-up "{subject}"',
    due: 'Due {date}',
    aiBadge: 'Suggested by AI',
  },
  state: {
    toHandle: 'To handle',
    relanceDue: 'Reminder due',
    late: 'Late',
    waiting: 'Waiting',
    done: 'Done',
  },
  settings: {
    title: 'Settings',
    language: 'Language',
    languageHelp: "The interface language. Oscar's tone is reviewed for each language.",
  },
}
