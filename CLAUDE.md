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

## État
- Fait : front « Mes suivis » avec API simulée (`web/src/api/suivis.ts`), réglage de langue.
- Prochaine tranche : squelette Symfony, entité Suivi, endpoints `GET /api/suivis` et `POST /api/suivis/{id}/close`, puis connexion IMAP et liste des mails.
- Reporté : nom commercial, vérification nom/domaine/marque, modèle économique, mobile (MVP 2-3).
