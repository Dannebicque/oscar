<script setup lang="ts">
/** Icônes décoratives : toujours doublées d'un texte (jamais le seul signal, U-08). */
defineProps<{ name: string; size?: number }>()

const PATHS: Record<string, string> = {
  home: 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10',
  mail: 'M3 6h18v12H3zM3 7l9 7 9-7',
  follow: 'M5 3v18M5 4h11l-2 4 2 4H5',
  calendar: 'M4 5h16v15H4zM4 10h16M8 3v4M16 3v4',
  check: 'M4 12l5 5L20 6',
  checkCircle: 'M12 21a9 9 0 100-18 9 9 0 000 18zM8 12l3 3 5-6',
  bell: 'M6 16V11a6 6 0 1112 0v5l2 2H4zM10 21h4',
  settings: 'M12 15a3 3 0 100-6 3 3 0 000 6zM19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3 1a7 7 0 00-2-1.2L14 3h-4l-.6 2.7a7 7 0 00-2 1.2l-2.3-1-2 3.4 2 1.5A7 7 0 005 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-1c.6.5 1.3.9 2 1.2L10 21h4l.6-2.7c.7-.3 1.4-.7 2-1.2l2.3 1 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z',
  plus: 'M12 5v14M5 12h14',
  sparkles: 'M12 3l1.8 4.6L18 9.5l-4.2 1.9L12 16l-1.8-4.6L6 9.5l4.2-1.9zM18 15l.9 2.1L21 18l-2.1.9L18 21l-.9-2.1L15 18l2.1-.9z',
  clock: 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2',
  alert: 'M12 3l10 18H2zM12 10v5M12 18v.01',
  send: 'M3 11l18-8-8 18-2-8z',
  reply: 'M9 8L4 13l5 5M4 13h10a6 6 0 016 6',
  replyAll: 'M7 8L2 13l5 5M12 8L7 13l5 5M7 13h8a6 6 0 016 6',
  forward: 'M15 8l5 5-5 5M20 13H10a6 6 0 00-6 6',
  archive: 'M3 5h18v4H3zM5 9v10h14V9M10 13h4',
  trash: 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13',
  inbox: 'M3 13l3-8h12l3 8v6H3zM3 13h5l1 3h6l1-3h5',
  draft: 'M4 20h4L19 9l-4-4L4 16zM13 7l4 4',
  folder: 'M3 6h6l2 2h10v11H3z',
  users: 'M9 11a3 3 0 100-6 3 3 0 000 6zM3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 11a3 3 0 100-6M18 14c2 .6 3 2.5 3 6',
  paperclip: 'M20 11l-8 8a5 5 0 01-7-7l9-9a3.5 3.5 0 015 5l-9 9a2 2 0 01-3-3l8-8',
  search: 'M11 18a7 7 0 100-14 7 7 0 000 14zM20 20l-4-4',
  undo: 'M9 14L4 9l5-5M4 9h10a6 6 0 010 12h-3',
  x: 'M6 6l12 12M18 6L6 18',
  chevronRight: 'M9 6l6 6-6 6',
  chevronLeft: 'M15 6l-6 6 6 6',
  chevronDown: 'M6 9l6 6 6-6',
  chevronUp: 'M6 15l6-6 6 6',
  user: 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 3.6-7 8-7s8 3 8 7',
  lock: 'M6 11h12v9H6zM8 11V8a4 4 0 118 0v3',
  eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z',
  eyeOff: 'M3 3l18 18M10.6 6.1A9.7 9.7 0 0112 5c6 0 10 7 10 7a17 17 0 01-3.2 4M6.2 6.7A17 17 0 002 12s4 7 10 7a9.6 9.6 0 004.2-1M9.9 9.9a3 3 0 004.2 4.2',
  refresh: 'M20 11A8 8 0 006 7L4 9M4 4v5h5M4 13a8 8 0 0014 4l2-2M20 20v-5h-5',
  link: 'M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7L12 6M14 10a4 4 0 00-5.7 0l-3 3A4 4 0 0011 18.7L12 18',
  note: 'M5 4h14v12l-4 4H5zM15 20v-4h4M8 9h8M8 13h5',
  list: 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
  board: 'M4 4h5v16H4zM10 4h5v10h-5zM16 4h4v13h-4z',
  monitor: 'M3 5h18v11H3zM8 21h8M12 16v5',
  sun: 'M12 16a4 4 0 100-8 4 4 0 000 8zM12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5',
  moon: 'M20 14A8 8 0 019.5 4 8 8 0 1020 14z',
  contrast: 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 3v18M12 3a9 9 0 010 18',
  task: 'M5 4h14v16H5zM9 9l2 2 4-4M9 16h6',
  star: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
  mapPin: 'M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11zM12 12a2 2 0 100-4 2 2 0 000 4z',
  download: 'M12 4v12M7 11l5 5 5-5M5 20h14',
  edit: 'M4 20h4L19 9l-4-4L4 16z',
  pause: 'M8 5v14M16 5v14',
  hourglass: 'M6 3h12M6 21h12M7 3c0 5 5 6 5 9s-5 4-5 9M17 3c0 5-5 6-5 9s5 4 5 9',
  sidebar: 'M3 5h18v14H3zM15 5v14',
}
</script>

<template>
  <svg :width="size ?? 20" :height="size ?? 20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path :d="PATHS[name] ?? PATHS.alert" />
  </svg>
</template>
