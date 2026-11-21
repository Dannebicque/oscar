# Conformité au cahier des charges v2 et aux maquettes

Passe du 21 novembre 2026. Source : cahier des charges v2 (lu en entier, 88 identifiants F, N et U) et maquette cliquable (19 fichiers).
Légende : ✅ fait et testé · 🟡 partiel (ce qui manque est dit) · ❌ pas fait · ⏸ hors périmètre décidé.
« Testé » = test automatisé (`api/tests`, `web/src/test`) ; plusieurs écrans ont aussi été parcourus dans un navigateur contre la pile réelle
(`scripts/dev-up.sh`). Le tableau exigence par exigence plus court, limité au MVP, reste dans [MVP.md](MVP.md).

## Bilan

| Bloc | ✅ | 🟡 | ❌ |
|---|---|---|---|
| Exigences fonctionnelles F (65) | 44 | 20 | 1 |
| Non fonctionnelles N (12) | 2 | 9 | 1 |
| Interface U (11) | 6 | 4 | 1 |

**MVP (priorité M)** : toutes les exigences M ont une implémentation ; les points qui les rendent 🟡 sont listés plus bas (webmail incomplet, LDAP, notifications bureau, application Tauri non commencée, pas d'image de production).

## 1. Exigences fonctionnelles

### 4.1 Récapitulatifs et vues
| ID | P | État | Remarques |
|---|---|---|---|
| F-01 | M | ✅ | Deux récaps/jour, horaires et fuseau réglables, un seul envoi par créneau (unicité testée). |
| F-02 | M | ✅ | Mails importants, événements, tâches du jour et en retard, relances dues. Agenda perso exclu sauf réglage. |
| F-03 | M | 🟡 | Mail ✅. L'écran « Récapitulatif » (résumé d'Oscar, lignes typées et liées) est calculé par le front ; il n'affiche pas le dernier récap réellement envoyé. |
| F-04 | S | 🟡 | Vues jour, semaine, mois, liste (FullCalendar) pour les événements. Pas de vue qui mélange mails, événements et tâches. |

### 4.2 Mail
| ID | P | État | Remarques |
|---|---|---|---|
| F-10 | M | 🟡 | Synchro IMAP incrémentale par UID, multi-comptes, lecture, envoi SMTP ✅. Assistant de connexion en quatre étapes (adresse, accès, serveurs devinés d'après le domaine, test des deux serveurs avant d'enregistrer) ✅, garde anti-SSRF en production. Recherche sur les en-têtes chargés, pas de plein texte serveur. |
| F-11 | M | 🟡 | Suggestion de tâche/événement avec accepter, modifier, ignorer ✅. Fournisseur local à règles ; aucun modèle de langage branché. |
| F-12 | M | ✅ | Relance à 48 h par défaut, délai réglable par utilisateur, **par compte** (Réglages > Comptes > Relances) et par suivi ; heures réelles ou jours ouvrés, au niveau de l'utilisateur ou du compte (API et front, testés). |
| F-13 | S | 🟡 | Brouillon de réponse (ton cordial/court, créneaux cochés), jamais envoyé ✅. Gabarit déterministe, pas de génération par modèle. |
| F-14 | S | 🟡 | Résumé extractif d'un message. Pas de résumé d'un fil entier. |
| F-15 | S | ✅ | Règles de tri (Réglages > Relances et suivi) par expéditeur, domaine ou mot de l'objet : archive ou corbeille à la synchronisation, jamais de suppression définitive, jamais un mail qui a un suivi ; activables, supprimables. Testé (règles, déplacement IMAP simulé). |
| F-16 | M | 🟡 | Dossiers, liste, lecture texte, répondre, répondre à tous, filtres, fil de discussion ✅. Archiver, déplacer (boîte de réception, archives, corbeille), supprimer (= corbeille, jamais définitif) ✅ : déplacement IMAP d'abord, base ensuite, annulation depuis la notification, vérifié contre Dovecot. Transférer ✅ (rédaction pré-remplie, message cité). Pièces jointes ✅ : liste et téléchargement seulement (jamais affichées, type forcé, `nosniff`), envoi jusqu'à 5 Mo, vérifié contre Dovecot et le SMTP de test. Brouillons enregistrés ✅ (dossier Drafts du serveur, rouvrables, remplacés à chaque enregistrement ; texte seul, sans pièces jointes). Manquent : vidage de la corbeille, affichage HTML isolé. |
| F-17 | C | ✅ | Trois styles (Moderne, Rétro, Néon), cinq palettes + accent libre, quatre modes, trois densités ; WCAG AA vérifié par test sur chaque combinaison. |
| F-18 | S | 🟡 | Annuaire LDAP écrit (lecture seule, compte de service, attributs configurables par variables d'environnement, saisie échappée contre l'injection de filtre) ; choix service et téléphone par utilisateur ; repli sur la liste de démonstration. **Jamais essayé contre un vrai annuaire** (testé avec un faux client). |
| F-19 | S | ✅ | Signature par compte (Réglages > Comptes > Réglages du compte), signature par défaut sinon ; modèles de message (nom, texte, variables `{prenom}` et `{moi}`) à insérer depuis la rédaction, signature conservée en bas. |

### 4.3 Agenda
| ID | P | État | Remarques |
|---|---|---|---|
| F-20 | M | ✅ | CalDAV lecture/création (récurrences, écriture serveur d'abord). Testé contre des réponses simulées, **jamais contre un vrai serveur**. Google : ❌ (v2). Un événement existant ne se modifie ni ne se supprime. |
| F-21 | S | ✅ | Chevauchements signalés dans la grille et dans le détail de l'événement. |
| F-22 | S | ✅ | Créneaux libres proposés depuis une demande de disponibilités (libre/occupé seulement). |
| F-23 | C | ✅ | Dans le panneau de l'événement sélectionné : « Créer une tâche de préparation » (échéance la veille, jour ouvré précédent ; origine « événement »). Jamais automatique. |
| F-24 | S | 🟡 | Plusieurs agendas affichés ensemble, bascule par calendrier, création dans l'agenda choisi ✅ ; une seule couleur par type (pro / perso), pas une couleur par source. |
| F-25 | S | 🟡 | Invitation iCalendar reçue par mail (`METHOD:REQUEST`) : carte dans le lecteur avec accepter, peut-être, refuser ; la réponse (iTIP `REPLY`) part à l'organisateur sur clic seulement, l'événement entre dans l'agenda choisi sans doublon, chevauchements signalés ; testé et vérifié sur la pile réelle. Manque : envoyer une invitation, notification « invitation reçue » (il faudrait lire le corps à la synchro), mises à jour et annulations. |
| F-26 | C | ✅ | Bascule « Équipe : disponibilités » dans l'agenda : créneaux occupés (« Occupé · Claire ») des collègues de la même boîte partagée qui ont activé « Partager mon agenda pro » (désactivé par défaut). Agenda pro seulement, jamais de titre, de lieu ni de participant ; testé côté API. |

### 4.4 Tâches
| ID | P | État | Remarques |
|---|---|---|---|
| F-30 | M | ✅ | Titre, description, échéance, 4 priorités, projet, étiquettes, état ; création, édition, suppression avec confirmation. |
| F-31 | M | ✅ | Agrège manuelles, issues de mails et d'événements ; origine toujours indiquée, lien « Voir le mail/l'événement » vers la liste (pas vers l'élément exact). |
| F-32 | M | 🟡 | Tri par échéance, priorité, projet. Pas « par heure de la journée ». |
| F-33 | M | ✅ | Filtres cumulables par projet et étiquette. |
| F-34 | M | ✅ | « Traité » avec annulation ; liste « Terminées ». |
| F-35 | M | ✅ | Marque « à confirmer », confirmer/rejeter. |
| F-36 | M | 🟡 | Pas de tâche de relance liée : le suivi porte le signal. |

### 4.5 Mail collaboratif
| ID | P | État | Remarques |
|---|---|---|---|
| F-40 | S | ✅ | Boîte partagée avec membres et rôles (API). |
| F-41 | S | ✅ | Prise en charge, état, notes internes. |
| F-42 | S | ✅ | « X rédige » (collision). |
| F-43 | C | ❌ | Règles d'assignation automatique. |
| F-44 | S | ✅ | Un `Message-ID` reçu par quatre membres = une demande (testé, vérifié de bout en bout). |
| F-45 | S | ✅ | Historique par demande non modifiable. |

### 4.6 Suivi des demandes
| ID | P | État | Remarques |
|---|---|---|---|
| F-50 | M | ✅ | À l'envoi, depuis un mail reçu, par règle ; suivi créé seulement si le message est parti. |
| F-51 | M | ✅ | États nouveau / en cours / en attente / traité ; signaux (retard, relance due, réponse reçue) calculés, jamais stockés. |
| F-52 | M | ✅ | In-Reply-To/References ; réponse automatique ignorée ; bouton « garder en attente ». |
| F-53 | M | ✅ | Relance proposée avec brouillon ; envoyer, reporter, ne plus relancer. |
| F-54 | M | ✅ | Voir F-12 : délai et jours ouvrés par compte ✅, 48 h par défaut. |
| F-55 | S | ✅ | Aucune relance automatique ; « ne plus relancer ». |
| F-56 | M | ✅ | Échéance propre, alerte et tri par retard. |
| F-57 | S | 🟡 | Un seul responsable ✅ ; pas d'écran d'assignation ni de transfert. |
| F-58 | S | ✅ | Journal non modifiable. |
| F-59 | M | 🟡 | Vue « Mes suivis » avec tuiles de situation ✅ ; filtres par compte, projet et étiquette ❌. |
| F-60 | S | ✅ | Périmètre « Équipe » avec état et ancienneté (dans la page Suivis, pas d'écran dédié). |
| F-61 | M | ✅ | Relances et retards dans les récaps ✅ ; la page Tâches signale « N relances à faire » avec un lien vers le mode Relances. |
| F-62 | S | 🟡 | Repère, échéance, relance rédigée, synthèse du jour ✅ ; pas de résumé de l'historique d'une demande. |
| F-63 | M | 🟡 | Entrée groupée, mode Relances, plafond quotidien, jamais d'envoi groupé ✅ ; regroupement des relances au même destinataire ❌. |
| F-64 | S | ✅ | Carte « Suggéré par l'IA », champs modifiables, rien sans clic ; refus non mémorisé. |
| F-65 | M | ✅ | Réponse reçue → « à traiter » + alerte, jamais de clôture automatique. |
| F-66 | S | ✅ | Règles par expéditeur ou domaine (Réglages > Relances et suivi) : visibles, désactivables, supprimables ; la règle de boîte partagée y est rappelée. Les messages automatiques sont ignorés, pas de doublon. |
| F-67 | S | ✅ | Sans responsable : nouveau / pris par X / traité par X ; reprise, libération. |
| F-68 | S | ✅ | Avertissement « X s'en occupe », indicateur « X rédige », notification des autres membres. |
| F-69 | S | ✅ | Notes internes ; IA coupée par défaut sur une boîte partagée. |
| F-70 | M | ✅ | Priorités classées avec raison, tableau, filtres par type et par situation, Mes suivis / Équipe, synthèse d'Oscar, activité récente. |
| F-71 | M | ✅ | Prochain RDV (en cours ou suivant, ensuite, journée terminée), agenda perso optionnel. **Avant ce rendez-vous** : les trois derniers échanges de mails (90 jours) avec les participants lus dans l'agenda (ORGANIZER et ATTENDEE), sinon avec un mot du titre dans l'objet ; sur l'accueil et dans le détail d'un événement. Calcul local : l'IA ne reçoit rien (ni titres, ni participants). |
| F-72 | M | ✅ | Accueil allégé, trois états dont premier lancement en quatre étapes. |
| F-73 | M | ✅ | Destinataires avec annuaire (LDAP si configuré), Cc/Cci, objet, signature, suivi précoché avec délai du compte et date d'alerte annoncée, **suggestion de tâche pendant la rédaction** (« avant mardi ») : proposée, cochée par l'utilisateur, créée seulement si le message part. **Annuler l'envoi** : après le clic, le message attend 10 s par défaut (0, 5, 10, 20 ou 30 s dans Réglages) ; « Annuler » le rouvre dans l'éditeur, « Envoyer maintenant » saute l'attente ; rien n'est parti ni créé pendant l'attente. **Mails programmés** : l'utilisateur choisit l'heure (demain matin, lundi matin, après-midi, ou date libre), le serveur envoie à l'heure (planificateur chaque minute, jamais deux fois), dossier « Programmés » pour annuler ou modifier ; un échec est signalé par une alerte et le message reste. La tâche cochée n'est pas créée pour un mail programmé (elle ne naît qu'à l'envoi). |
| F-74 | S | 🟡 | Créneaux libres cochables, brouillon modifiable avec ton, rien sans clic ✅. Pose en provisoire dans l'agenda ❌ ; bascule « agenda perso » seulement dans Réglages. |

### 4.7 Notifications, connexions, journal
| ID | P | État | Remarques |
|---|---|---|---|
| F-75 | M | 🟡 | Un canal par type d'événement (application, bureau, e-mail) dans Alertes ✅, une seule alerte par fait sur chaque canal, e-mail testé. Le canal « bureau » est écrit côté front (notifications natives) mais l'application Tauri n'a pas été compilée. |
| F-76 | M | ✅ | Réponse reçue / relance due en tête des priorités avec le motif. |
| F-77 | M | ✅ | Barre d'état : messagerie, agendas, IA avec mode ; détail et « Réessayer ». |
| F-78 | M | 🟡 | Journal exportable, filtre par compte, cartes de connexion. Pas de filtre par niveau (le journal n'a pas de gravité). |
| F-79 | M | ✅ | Messages clairs en français avec action ; alerte seulement après 3 échecs, une fois par jour. |
| F-80 | S | 🟡 | Message écrit sans réseau : mis en file (IndexedDB, mémoire sinon), parti dans l'ordre au retour de la connexion avec confirmation ; un refus du serveur n'est jamais mis en file. Bannière « hors ligne » annoncée. Testé en unitaire ; **jamais essayé dans un vrai navigateur coupé du réseau**. |

## 2. Exigences non fonctionnelles

| ID | État | Remarques |
|---|---|---|
| N-01 | 🟡 | axe-core automatisé sur chaque écran, clavier, focus, réduction des animations. Pas de test lecteur d'écran réel. |
| N-02 | 🟡 | Zones live, sans vol de focus ; vérification à l'oreille manquante. |
| N-03 | 🟡 | Annulation des actions courantes ✅. Capture rapide : touche `q` dans l'application, depuis n'importe quel écran ✅ ; raccourci global du bureau écrit (Tauri), non essayé. |
| N-04 | 🟡 | Identifiants chiffrés (libsodium), clé hors base ✅. Trousseau du système sur bureau : commandes écrites (crate `keyring`), non compilées. HTTPS non contrôlé ici. |
| N-05 | 🟡 | CAS testé sur des réponses simulées, **jamais contre le CAS réel**. Comptes locaux ✅ : inscription (désactivable, `APP_REGISTRATION`), connexion avec essais limités et message identique que le compte existe ou non, mot de passe oublié (lien à usage unique d'une heure, réponse neutre), changement de mot de passe (autres appareils déconnectés). Sessions révocables ✅ ; TOTP/passkey ❌. |
| N-06 | 🟡 | Export JSON de mes données et suppression définitive du compte (cascade) ✅, depuis Réglages > Mon compte. Pas de durées de conservation réglables ni de registre des traitements. Le journal des appels IA ne contient aucun contenu. |
| N-07 | ❌ | Performances non mesurées (100 000 mails). |
| N-08 | ✅ | Aucun récap ni alerte en double (unicité testée). |
| N-09 | 🟡 | Service worker : coque de l'application en cache, lectures d'API (tâches, suivis, mails récents, agenda…) servies du cache hors ligne, effacées à la déconnexion ; file d'envoi (F-80). Écrit et construit, **non vérifié dans un navigateur** (le service worker n'a pas pu s'enregistrer dans le navigateur intégré de l'outil de développement). Pas de synchronisation des modifications faites hors ligne (tâches, suivis). |
| N-10 | ✅ | fr/en, aucune chaîne en dur, clés vérifiées par test. |
| N-11 | 🟡 | Dev en une commande ✅. Image de production (`Dockerfile`, FrankenPHP), `compose.prod.yaml` (base, application, worker), modèle `docker/prod/env.example` et guide `docs/INSTALL.md` écrits ; Compose validé, **image jamais construite ni essayée**. CI écrite, jamais exécutée sur GitHub.  Fichier `LICENSE` (AGPL v3) ajouté. |
| N-12 | 🟡 | Interface moderne, accessible, raccourcis Gmail. Gestes webmail courants présents ; vidage de la corbeille et HTML isolé manquent (F-16). |

## 3. Interface (section 10)

| ID | P | État | Remarques |
|---|---|---|---|
| U-01 | M | ✅ | Barre du bas à cinq boutons (Suivis au centre) sur mobile, rail latéral au bureau avec « Suivis » mis en avant. |
| U-02 | S | 🟡 | Balayage horizontal entre les cinq vues principales (écran tactile seulement), toujours doublé par les boutons ; ignoré dans un champ, une fenêtre, l'agenda ou un contenu qui défile. Logique testée ; **geste jamais essayé sur un écran tactile**. |
| U-03 | M | 🟡 | Un seul code adaptatif ✅ ; PWA : manifeste, icônes (192, 512, maskable), service worker écrits ; installation non essayée. |
| U-04 | M | 🟡 | Chaque bloc de l'accueil ouvre sa vue ; retour par bouton seulement, pas par geste. |
| U-05 | M | ✅ | Le seul geste (balayage) est doublé par les boutons de navigation ; actions au clavier et au pointeur simple partout. |
| U-06 | S | 🟡 | Bloc « Oscar » de la synthèse avec recommandations ✅ ; échange conversationnel ❌ (second temps prévu). |
| U-07 | M | ✅ | Résumé d'Oscar avec lien vers le récap en tête de l'accueil. |
| U-08 | M | ✅ | Icône + libellé partout, jamais la couleur seule ; contrastes vérifiés. |
| U-09 | S | ✅ | Trois styles, cinq palettes, accent libre, modes clair/sombre/contraste. |
| U-10 | S | ❌ | Ordre de repli des panneaux paramétrable. |
| U-11 | S | ✅ | Raccourcis alignés sur Gmail, désactivables : c, q, /, j, k, r, **e (archiver), # ou Suppr (corbeille), s (suivre)**, g puis i/s/t/a/h, ?. Après archivage ou corbeille, la liste passe au message suivant (traitement à la chaîne) ; l'annulation reste proposée. |

## 4. Écrans prévus (section 10.2)

| Écran | État | Remarques |
|---|---|---|
| Aujourd'hui | ✅ | |
| Réception | 🟡 | Voir F-16 (corbeille, HTML). |
| Mes suivis | ✅ | |
| Tâches | ✅ | Listes + détail comme la maquette. |
| Réglages | ✅ | Mon compte, comptes avec délai de relance et signature, modèles de message, annuaire, règles de suivi, IA externe, apparence. Reste à comparer ligne à ligne à la maquette. |
| Agenda | ✅ | Grille, bascule par calendrier, panneau « événement sélectionné », échéances, conflits, invitations reçues (réponse depuis le mail). |
| Équipe | 🟡 | Périmètre « Équipe » de la page Suivis ; pas d'écran dédié. |
| Écrire | 🟡 | Voir F-73. |
| Récapitulatif | ✅ | |
| État et notifications | ✅ | Barre d'état, notifications avec choix des canaux, journal. |
| Réponse assistée | 🟡 | Voir F-74. |

## 5. Maquettes : comparaison écran par écran

Comparaison de textes (maquette contre catalogue français) puis contrôle visuel pour les écrans marqués « vu ». Les écarts de texte qui ne sont que des données d'exemple ne sont pas comptés.

| Maquette | Contrôle | Écarts constatés |
|---|---|---|
| DesktopSuivis | vu | Fait : tuiles, pastilles de type, synthèse, activité, entrée de menu. Reste : « Prochain RDV » avec boutons d'heure simulée (propre à la maquette, volontairement absent). |
| DesktopTaches | vu | Fait : listes, détail, origine. Reste : suggestion « terminer clôt le suivi lié » (pas de lien tâche → suivi). |
| DesktopRecap | vu | Fait : résumé, compteurs, lignes liées. Reste : en-tête « De : Oscar · À : … » et dernier envoi réel. |
| DesktopEtat | vu | Fait : cartes de connexion, journal exportable. Reste : « détail technique pour le support », filtre par niveau, ligne Google/CalDAV selon l'API. |
| DesktopReponseIA | vu | Fait : mail d'origine, ton, régénérer, créneaux en colonne. Reste : provisoire, « réserver les créneaux », bascule agenda perso dans l'écran. |
| DesktopMail / Lecture | vu | Fait : trois volets, bandeau et volet de suivi, fil, résumé, suggestions. Archiver, déplacer, supprimer et transférer agissent . |
| DesktopAccueil / Main | vu | Fait : trois états. Reste : bloc « Recommandations » dédié (la synthèse d'Oscar le remplace). |
| DesktopComposer | partiel | Reste : bloc « Suggéré par l'IA · à vérifier » pour la tâche à créer à l'envoi, bandeau LDAP, identité d'expéditeur. |
| DesktopAlias | non comparé | Les états nouveau / pris / traité, notes et collision existent ; pas d'écran dédié « boîte partagée ». |
| DesktopAgenda / MobAgenda | partiel | Fait : bascule de calendriers, panneau de l'événement sélectionné, échéances de la période, invitation reçue à accepter. |
| DesktopNotifs | partiel | Fait : « Comment être prévenu » (canal par type). |
| DesktopReglages | partiel | Fait : LDAP, règles de suivi, délai par compte. Reste à comparer le détail. |
| MobTaches, Suivis (mobile), Relance | partiel | Relance : brouillon, envoyer, reporter, ne plus relancer présents. Mise en page mobile contrôlée seulement par les tests de gabarit. |

## 6. Architecture et garde-fous IA (sections 3 et 5)

| Point | État | Remarques |
|---|---|---|
| Symfony + API Platform, PostgreSQL, Messenger/Scheduler | ✅ | Tests sur SQLite et PostgreSQL. |
| Webklex, Symfony Mailer, sabre/vobject | ✅ | |
| Reka UI, Pinia, Vue Router, vue-i18n | ✅ | FullCalendar pour l'agenda. |
| Mercure (temps réel) | ❌ | Interrogation du serveur chaque minute. |
| FrankenPHP | 🟡 | Image et Caddyfile écrits (`Dockerfile`, `docker/prod/`) ; jamais construits. `php -S` en développement. |
| Cache hors ligne (service worker, IndexedDB) | 🟡 | Écrit et testé en unitaire ; non vérifié dans un navigateur. |
| Tauri 2 | 🟡 | Socle écrit dans `desktop/` (trousseau, notifications natives, raccourci global, barre système, instance unique, icônes, CI manuelle) et pont côté web testé. Le code Rust compile (check, clippy, build) et l'application se lance sur macOS avec le front ; **trousseau, notifications, raccourci global, Windows et Linux non vérifiés**. |
| Playwright | ❌ | Remplacé par `scripts/e2e.py` (HTTP + IMAP réels). |
| Affichage HTML isolé (iframe sandbox) | ❌ | Texte brut seulement : aucun contenu actif ne s'exécute. |
| Identifiants chiffrés, clé hors base | ✅ | |
| Texte d'un mail = donnée, jamais une consigne | ✅ | Test dédié sur un mail hostile. |
| Agenda : libre/occupé seulement vers l'IA | ✅ | Testé ; agenda perso seulement si activé. |
| Journal des appels IA sans contenu | ✅ | |
| IA interdite par compte | ✅ | Par dossier ❌. |
| Boîte partagée : IA seulement activée par un administrateur | ✅ | Coupée par défaut. |
| IA de l'université par défaut, externe désactivée | ✅ | Mode visible dans la barre d'état. |
| Fournisseurs Ollama et cloud | 🟡 | Fournisseur externe configurable par l'utilisateur (OpenAI, Mistral, Anthropic, Ollama, tout service compatible OpenAI) : clé chiffrée, jamais renvoyée, test de connexion, ce qui est envoyé affiché. Testé avec des réponses simulées, **jamais contre un vrai service**. Pas de modèle auto-hébergé fourni. |
| Sortie JSON validée par schéma | ✅ | Chaque champ de la sortie d'un modèle externe est validé (dates, longueurs) ; une valeur invalide est ignorée au profit de la proposition locale. |

## 7. Ce qui reste à faire, par ordre de valeur

1. **Webmail (F-16)** : vidage de la corbeille, HTML isolé, pièces jointes dans les brouillons.
2. **Fournisseur d'IA** : l'essayer contre un vrai service (OpenAI, Mistral, Ollama) et brancher un modèle auto-hébergé pour l'IA de l'université.
3. **Tauri** : compiler `desktop/`, corriger, signer, mises à jour automatiques, essais sur les trois systèmes et lecteurs d'écran.
4. **Agenda** : envoi d'invitations et mises à jour (F-25), vrai serveur CalDAV de test, Google.
5. Vérifier la PWA et le hors-ligne dans un vrai navigateur ; synchroniser les modifications faites hors ligne.
6. **Production** : construire et essayer l'image, test du CAS et du LDAP réels, durées de conservation (RGPD).
7. **Suivi** : tâche liée à un suivi (F-36), filtres par compte/projet/étiquette (F-59), regroupement des relances au même destinataire.
