# Oscar, votre majordome numérique

Assistant d'organisation professionnelle (mail, agenda, tâches) centré sur le suivi des conversations.

- `web/` : interface Vue 3 + Vite + TypeScript (Pinia, Vue Router, vue-i18n fr/en).
- `api/` : backend Symfony 7 + API Platform (à initialiser, voir `api/README.md`).
- `desktop/` : enveloppe Tauri 2, à ajouter une fois le flux principal en place.

## Démarrer le front

```bash
cd web
npm install
npm run dev     # http://localhost:5173, données simulées
npm test
npm run build
```

La première tranche (page « Mes suivis », réglage de langue) fonctionne avec une API simulée.
