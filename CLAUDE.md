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
- Fait : prototype front complet sur API simulée (Accueil, Suivis priorités/tableau + mode Relances, Mails avec suivi et boîte partagée, Écrire, Agenda, Tâches, Récap, Alertes, Journal, Réglages), thèmes (clair, sombre, contraste), densités, fr/en, tests (domaine, flux, axe-core).
- Fait : API Symfony avec entité Suivi (GET, POST, PATCH, close). Le front l'utilise pour les suivis si `VITE_API_URL` est défini.
- Fait : serveur IMAP de test `docker/dovecot/` (comptes, scénarios F-11, F-44, F-52).
- Fait : synchro IMAP côté API (webklex/php-imap, incrémentale par UID, en-têtes seulement), entité Mail, `GET /api/mails`, détection de réponse par `In-Reply-To`/`References` (réponse automatique ignorée, jamais de clôture), regroupement des alias par Message-ID. Commande `php bin/console app:mail:sync`. Tests PHPUnit dont l'intégration Dovecot.
- Fait : le front lit les mails sur `/api/mails` quand `VITE_API_URL` est défini (corps en texte brut à la demande via `/api/mails/{id}/body`, rattachement au suivi par Message-ID et par le fil). Vérifié de bout en bout avec Dovecot.
- Pas encore côté serveur (échec explicite dans le front, rien ne fait semblant) : envoi SMTP, boîtes partagées (prise en charge, notes), suggestions IA, état lu.
- Prochaine tranche : envoi SMTP (Symfony Mailer) avec création du suivi à l'envoi, puis boîtes partagées côté API, entités Tâche/Événement (CalDAV), CAS, Mercure. Identifiants IMAP à chiffrer hors base avant tout usage réel.
- Reporté : nom commercial, vérification nom/domaine/marque, modèle économique, mobile natif, Tauri, hors ligne (S), réponse assistée IA réelle.
