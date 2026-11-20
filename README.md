# Oscar, votre majordome numérique

Assistant d'organisation professionnelle (mail, agenda, tâches) centré sur le **suivi** des conversations.
Cahier des charges v2 et maquette cliquable : voir `CLAUDE.md` pour les liens et les règles produit.

- `web/` : interface Vue 3 + Vite + TypeScript (Pinia, Vue Router, vue-i18n fr/en).
- `api/` : backend Symfony + API Platform (suivis : `GET/POST /api/suivis`, `PATCH /api/suivis/{id}`, `POST /api/suivis/{id}/close`).
- `docker/dovecot/` : serveur IMAP de test local (voir son README).
- `desktop/` : enveloppe Tauri 2, à ajouter une fois le flux principal en place.

## Démarrer le front

```bash
cd web
npm install
npm run dev     # http://localhost:5173, tout est simulé dans le navigateur
npm test        # unitaires, composants et audit d'accessibilité (axe-core)
npm run build
```

Pour brancher les suivis sur l'API Symfony, copier `web/.env.example` en `web/.env.local` et définir `VITE_API_URL`.

## Démarrer l'API

```bash
cd api
composer install
cp .env.example .env            # puis renseigner APP_SECRET (le .env n'est jamais versionné)
php bin/console doctrine:migrations:migrate -n
php bin/console app:suivis:seed
php -S 127.0.0.1:8000 -t public
```

## Thèmes

Les couleurs ne vivent que dans des jetons (`web/src/styles/tokens.css`). Un thème est un bloc `[data-theme]` dans
`web/src/styles/themes.css`, une entrée dans `web/src/themes/index.ts` et un libellé `settings.look.themes.<id>`.
Livrés : automatique, clair, sombre, contraste élevé.

## Tester l'étape IMAP

```bash
docker compose -f docker/dovecot/compose.yaml up -d
python3 docker/dovecot/seed.py && python3 docker/dovecot/smoke.py
```
