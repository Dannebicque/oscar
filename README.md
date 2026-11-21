# Oscar, votre majordome numérique

Assistant d'organisation professionnelle (mail, agenda, tâches) centré sur le **suivi** des conversations.
Cahier des charges v2 et maquette cliquable : voir `CLAUDE.md` pour les liens et les règles produit.

- `web/` : interface Vue 3 + Vite + TypeScript (Pinia, Vue Router, vue-i18n fr/en).
- `api/` : backend Symfony + API Platform (suivis : `GET/POST /api/suivis`, `PATCH /api/suivis/{id}`, `POST /api/suivis/{id}/close`).
- `docker/dovecot/` : serveur IMAP de test local (voir son README).
- `desktop/` : enveloppe Tauri 2, à ajouter une fois le flux principal en place.

## Tout lancer en local

```bash
scripts/dev-up.sh     # PostgreSQL + IMAP de test (Docker), API, worker, SMTP de test, front
scripts/dev-down.sh
```

Front : http://localhost:5173, connexion de développement avec `david`, `claire`, `marc` ou `lea` (quatre membres de l'alias `contact@`).
L'état exigence par exigence est dans [`docs/MVP.md`](docs/MVP.md).

## Tout tester d'un coup

```bash
scripts/test-all.sh          # démarre la pile si besoin, lance API (SQLite + PostgreSQL + IMAP réel), front, build et parcours de bout en bout
scripts/test-all.sh --keep   # laisse la pile tourner ensuite pour essayer à la main
python3 scripts/e2e.py       # seulement le parcours de bout en bout, sur une pile déjà lancée
```

## Tests

```bash
cd api && php bin/phpunit                                  # SQLite en mémoire
cd api && DATABASE_URL="postgresql://oscar:oscar-dev@127.0.0.1:5433/oscar_test?serverVersion=17" php bin/phpunit   # PostgreSQL
cd web && npm test                                         # domaine, flux, client HTTP, axe-core sur chaque écran
```

Les tests d'intégration IMAP (`api/tests/Mail/DovecotIntegrationTest.php`) sont ignorés si Dovecot ne tourne pas.

## Front seul

```bash
cd web && npm install && npm run dev
```

Sans API, `VITE_USE_MOCK=true npm run dev` lance la démo simulée hors ligne. Par défaut le front parle à `VITE_API_URL`
(`http://localhost:8000`).

## Composants

Les widgets interactifs reposent sur des bibliothèques maintenues plutôt que sur du code maison : **Reka UI** (MIT, composants accessibles sans style : fenêtres modales, interrupteurs, cases à cocher, choix exclusifs, notifications, pastilles de destinataires) et **FullCalendar 6** (MIT : agenda). Ils sont habillés par nos jetons de thème (`web/src/components/ui`, `web/src/components/agenda`).

## Thèmes et design system

Un thème = un **style** (forme, relief, typographie : Moderne, Rétro, Néon) × une **palette** d'accent (Azur, Émeraude, Ambre, Corail,
Graphite, ou une couleur libre) × un **mode** (automatique, clair, sombre, contraste élevé). Tout est calculé dans
`web/src/themes/engine.ts` et appliqué par jetons CSS sur `<html>` ; un accent choisi librement est corrigé pour rester lisible.
**Chaque combinaison est vérifiée par test contre WCAG 2.2 AA** (`web/src/test/themes.test.ts` : 60 combinaisons + accents libres).

L'aspect des cartes, bandeaux, lignes, pastilles et boutons vit en un seul endroit, `web/src/styles/components.css`
(composants `BaseCard`, `Callout`, `BaseButton`, `ToneChip`…). Un test (`designSystem.test.ts`) interdit de les redéfinir dans une
vue et de coder une couleur ou une police en dur. Polices embarquées (Inter, JetBrains Mono, Space Grotesk), aucun appel externe.

Ajouter un style : une entrée dans `engine.ts` (STYLES, neutrals, styleTokens) et un libellé `settings.look.styles.<id>`.
Ajouter une palette : une entrée dans PALETTES et un libellé `settings.look.palettes.<id>`.

Raccourcis clavier alignés sur Gmail (c, /, j, k, r, g puis i/s/t/a/h, ?), désactivables dans Réglages (WCAG 2.1.4).

## Sécurité

Identifiants de messagerie chiffrés (libsodium) avec une clé `APP_VAULT_KEY` hors base ; jetons d'accès opaques, hachés, révocables ;
données isolées par utilisateur ; connexion par CAS en production (connexion de développement limitée à `dev` et `test`).
