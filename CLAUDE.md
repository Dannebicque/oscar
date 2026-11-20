# Oscar, contexte du projet

Assistant d'organisation pro (mail, agenda, tâches) dont le cœur est le **suivi** des conversations.
Cahier des charges v2 et maquette cliquable : projet claude.ai « Agenda » (doc « Cahier des charges v2 »).

## Stack
- `api/` : Symfony 7 + API Platform, IMAP/SMTP, CalDAV (sabre/dav), Google Calendar API, LDAP. IA indépendante du fournisseur.
- `web/` : Vue 3 + Vite + TypeScript, Pinia, Vue Router, vue-i18n (fr, en). Pas de Nuxt (SPA, Tauri).
- `desktop/` : Tauri 2, à ajouter une fois le flux principal en place.
- Licence cible : AGPL pour le cœur.

## Règles produit (à ne pas casser)
- Un suivi par fil de conversation. Création : à l'envoi (« Suivre la réponse »), depuis un mail reçu (l'IA suggère, l'utilisateur valide), ou par règle.
- Une réponse reçue passe le suivi à « à traiter », jamais de clôture automatique.
- Les relances sont seulement suggérées, jamais envoyées seules ; plafond quotidien.
- Boîte partagée (alias) : aucun responsable désigné ; états nouveau / pris par X / traité par X, anti-doublon, notes internes.
- IA : par défaut hébergée par l'université ; fournisseur externe en option dans Réglages. Pour les agendas, l'IA ne lit que libre/occupé. L'IA suggère et prépare, ne décide ni n'envoie.

## UX et accessibilité
- Le type ou l'état est toujours dit par une icône ET un texte, jamais par la couleur seule.
- Éléments suggérés par l'IA : violet + pastille « Suggéré par l'IA ».
- Cibles tactiles ≥ 44 px, focus visible, aria-pressed / aria-expanded / aria-current, lien d'évitement.
- Aucune chaîne en dur : tout passe par vue-i18n (`web/src/i18n`).

## Ton d'Oscar
Majordome numérique (« Votre majordome numérique. »). Phrases courtes, jamais de reproche, humour léger qui disparaît en cas d'erreur, toujours une action proposée. Tutoiement dans l'application.

## Références
- Cahier des charges v2 (doc claude.ai) : https://claude.ai/artifact/P1MTWboDnQZFWT79qR9i4k : source de vérité, exigences F-xx / N-xx / U-xx.
- Maquette cliquable (18 écrans mobile et bureau) : https://claude.ai/artifact/Rhjp3aw2sqYzAKeLYRewbq
- Lire le cahier des charges avant toute fonctionnalité : citer l'ID de l'exigence dans les commits et les tests.

## Modèle de suivi (F-50 à F-56)
- Un sens (`toHandle` / `waiting`) et un état (`new` / `inProgress` / `waiting` / `done`).
- « En retard », « relance due », « réponse reçue » sont des signaux calculés, jamais des états stockés.

## État
Voir `docs/MVP.md` : tableau exigence par exigence (✅ / 🟡 / ❌), à tenir à jour à chaque tranche.

- Dev en une commande : `scripts/dev-up.sh` (PostgreSQL + Dovecot en tmpfs, API, worker, SMTP de test, front) ; `scripts/dev-down.sh`.
- Tests : `cd api && php bin/phpunit` (SQLite en mémoire ; `DATABASE_URL=postgresql://…` pour PostgreSQL), `cd web && npm test` (dont axe-core).
- Le front parle à l'API par défaut ; `VITE_USE_MOCK=true` pour la démo hors ligne.
- Backend : API Platform + Doctrine (PostgreSQL), Symfony Messenger/Scheduler, webklex/php-imap, Symfony Mailer, sabre/vobject (CalDAV), libsodium (coffre des identifiants).
- Règles à ne pas casser : aucun envoi sans clic ; le suivi n'est créé que si le message est parti ; aucune clôture ni relance automatique ; l'IA ne reçoit jamais titres, lieux ni participants d'un agenda ; mails personnels jamais visibles des autres membres d'un alias.
- Prochaine tranche : voir « Points de vigilance » de `docs/MVP.md` (CAS réel, CalDAV réel, image de production, Tauri).
