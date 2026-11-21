# État du MVP au regard du cahier des charges v2

> Vue complète (toutes les exigences, écrans et maquettes) : [CONFORMITE.md](CONFORMITE.md).

Légende : ✅ fait et testé · 🟡 partiel (ce qui manque est dit) · ❌ pas fait.
« Testé » = couvert par un test automatisé (`api/tests`, `web/src/test`) ; les écrans ont aussi été parcourus dans un navigateur
contre l'API, Dovecot et le SMTP de test réels (`scripts/dev-up.sh`).

## Exigences du MVP (priorité M)

| ID | Exigence | État | Remarques |
|---|---|---|---|
| F-01 | Deux récaps par jour, horaires réglables, fuseau de l'utilisateur | ✅ | Planificateur Symfony (`CheckRecapsMessage`), un seul récap par créneau et par jour. |
| F-02 | Contenu du récap | ✅ | Mails importants, événements du jour, tâches du jour et en retard, relances dues. Agenda perso exclu sauf réglage. |
| F-03 | Diffusion par e-mail et accueil, canaux désactivables | 🟡 | E-mail ✅ ; l'écran « Récapitulatif » existe mais lit les données du front, il n'affiche pas encore le dernier récap envoyé. |
| F-10 | Synchro IMAP multi-comptes, lecture, recherche, envoi SMTP | 🟡 | Synchro incrémentale par UID, lecture, envoi ✅. La recherche porte sur les en-têtes déjà chargés (pas de recherche plein texte serveur). |
| F-11 | Tâches proposées depuis un mail | 🟡 | Résumé extractif du message (carte « Résumé d'Oscar ») et brouillon de réponse (ton cordial ou court, créneaux cochés) ✅, jamais envoyés : l'utilisateur relit et clique. Fournisseur local à règles (dates en français « avant mardi », « jeudi à 10h »). Pas de modèle de langage branché : l'interface `AiProvider` l'attend. |
| F-12 | Relance après 48 h, délai réglable | 🟡 | Délai réglable par utilisateur et par suivi (via l'échéance). Pas par compte ; « jours ouvrés » est enregistré mais **pas encore appliqué** au calcul. |
| F-16 | Webmail convaincant | 🟡 | Dossiers, liste, lecture (texte brut), répondre, filtres ✅. Archiver, déplacer, supprimer (corbeille), transférer ✅ et fil de discussion ✅. Pièces jointes ✅ (téléchargement et envoi jusqu'à 5 Mo). **Pas encore** : brouillons enregistrés, HTML isolé. |
| F-20 | Agenda CalDAV, lecture et création | ✅ | Récurrences développées ; création écrite d'abord sur le serveur. Affichage par FullCalendar 6 (MIT, maintenu) : semaine, jour, mois, liste, glisser pour créer, événements focalisables au clavier. Un événement existant se consulte mais ne se modifie ni ne se supprime pas encore. Testé contre des réponses CalDAV simulées, **pas contre un vrai serveur CalDAV**. Google : version 2. |
| F-30 à F-35 | To-do globale, tri, filtres, « traité », « à confirmer » | ✅ | |
| F-36 | Réponse reçue → tâche de relance liée | 🟡 | Il n'y a pas de tâche de relance liée ; c'est le suivi qui porte le signal. |
| F-50 à F-56 | Suivi : création, états, réponse, relance, échéance | ✅ | Sens + état stockés, signaux calculés, aucune clôture ni relance automatique. |
| F-58 | Journal non modifiable | ✅ | |
| F-59, F-70 | Mes suivis, page Suivis (priorités, tableau) | ✅ | Tuiles de situation filtrantes, synthèse d'Oscar, activité récente en colonne, entrée de menu mise en avant (maquette « centre de suivi »). |
| F-61 | Relances et retards dans récaps et to-do | 🟡 | Récaps ✅ ; pas de ligne dédiée dans la to-do. |
| F-63 | Mode Relances, plafond quotidien | ✅ | Jamais d'envoi groupé. |
| F-65 | Réponse reçue → « à traiter » + alerte, jamais de clôture auto | ✅ | |
| F-71, F-72 | Prochain RDV, accueil (3 états) | ✅ | |
| F-73 | Composition avec suivi à l'envoi | 🟡 | Suivi créé par le serveur seulement si le message part ✅. L'annuaire LDAP est une liste de démonstration : **LDAP non implémenté** (F-18, priorité S). |
| F-75 | Notifications | 🟡 | Dans l'application ✅ (regroupées, dédoublonnées). Notifications du bureau (Tauri) et e-mail : ❌. |
| F-76 à F-79 | Mise en avant, barre d'état, journal, messages clairs | ✅ | Alerte seulement après 3 échecs, une fois par jour. |

## Priorité S déjà faite (bonus)

Boîte partagée complète côté API et front (F-40 à F-45, F-60, F-66 à F-69) : une demande pour un même `Message-ID` reçu par plusieurs
membres, « pris par X » sans responsable désigné, anti-doublon, reprise avec information de l'ancien, indicateur « X rédige »,
notes internes, règle de création automatique désactivable, IA coupée par défaut sur une boîte partagée.
Réponse assistée aux disponibilités (F-74) côté suggestion et brouillon, avec lecture libre/occupé uniquement.

## Non fonctionnel

| ID | État | Remarques |
|---|---|---|
| N-01, N-02 | 🟡 | Audit axe-core automatisé sur chaque écran, zones live, focus, clavier. **Pas de test lecteur d'écran réel ni de mesure de contraste en navigateur.** |
| N-04 | ✅ | Identifiants chiffrés (libsodium), clé hors base. Le trousseau du système (bureau) n'existe pas encore. |
| N-05 | 🟡 | CAS implémenté (ticket validé côté serveur, code à usage unique, retour en liste blanche) et testé avec des réponses simulées, **jamais contre le CAS de l'établissement**. Sessions révocables ✅. TOTP/passkey ❌. |
| N-06 | ❌ | Pas d'export ni de suppression des données, pas de durées de conservation réglables. |
| N-07 | ❌ | Performances non mesurées (boîte de 100 000 mails). |
| N-08 | ✅ | Aucun récap ni alerte en double (contraintes d'unicité testées). |
| N-09 | ❌ | Pas de mode hors ligne ni de file d'envoi (F-80). |
| N-10 | ✅ | fr/en, clés vérifiées par test. |
| N-11 | 🟡 | Environnement de développement en une commande (`scripts/dev-up.sh`) ✅. **Image de production et Docker Compose de déploiement : ❌.** |
| U-01, U-03, U-08 | ✅ | Barre du bas, rail latéral, couleur jamais seule. |
| U-09, F-17 | ✅ | Trois styles (Moderne, Rétro, Néon), cinq palettes et accent libre corrigé pour le contraste, quatre modes, trois densités. Chaque combinaison vérifiée WCAG AA par test. |
| U-11 | ✅ | Raccourcis Gmail (c, /, j, k, r, g+i/s/t/a/h, ?), désactivables. |
| U-02 | ❌ | Balayage entre vues (S). |

## Hors du MVP actuel

Application Tauri (le cahier la place dans le MVP : **non commencée**), PWA, connecteur Google Agenda, invitations iCalendar (F-25),
partage d'agenda (F-26), règles de tri (F-15), signatures et modèles (F-19), LDAP (F-18), récap affiché sur l'accueil à partir du
dernier envoi, Mercure (temps réel : l'application interroge le serveur chaque minute).

## Points de vigilance avant un vrai usage

1. Tester le CAS et le LDAP de l'établissement (aucun accès ici).
2. Poser un vrai serveur CalDAV (Radicale, Baïkal) dans l'environnement de test.
3. Fournisseur d'IA : brancher Ollama ou un cloud derrière `AiProvider`, avec les garde-fous déjà testés (texte = donnée, libre/occupé seul).
4. Écrire l'image de production et la documentation d'installation (N-11).
5. Les migrations ont été regénérées pour PostgreSQL (cible du cahier) ; aucune base de production n'existait.
