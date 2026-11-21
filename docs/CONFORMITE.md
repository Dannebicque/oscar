# Conformité au cahier des charges v2 et aux maquettes

Passe du 21 novembre 2026. Source : cahier des charges v2 (lu en entier, 88 identifiants F, N et U) et maquette cliquable (19 fichiers).
Légende : ✅ fait et testé · 🟡 partiel (ce qui manque est dit) · ❌ pas fait · ⏸ hors périmètre décidé.
« Testé » = test automatisé (`api/tests`, `web/src/test`) ; plusieurs écrans ont aussi été parcourus dans un navigateur contre la pile réelle
(`scripts/dev-up.sh`). Le tableau exigence par exigence plus court, limité au MVP, reste dans [MVP.md](MVP.md).

## Bilan

| Bloc | ✅ | 🟡 | ❌ |
|---|---|---|---|
| Exigences fonctionnelles F (65) | 34 | 24 | 7 |
| Non fonctionnelles N (12) | 2 | 8 | 2 |
| Interface U (11) | 5 | 4 | 2 |

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
| F-12 | M | 🟡 | Relance par défaut à 48 h, réglable par utilisateur et par suivi ; heures réelles ou jours ouvrés appliqués au calcul (API et front, testés). Pas encore par compte. |
| F-13 | S | 🟡 | Brouillon de réponse (ton cordial/court, créneaux cochés), jamais envoyé ✅. Gabarit déterministe, pas de génération par modèle. |
| F-14 | S | 🟡 | Résumé extractif d'un message. Pas de résumé d'un fil entier. |
| F-15 | S | ❌ | Règles de tri déterministes. |
| F-16 | M | 🟡 | Dossiers, liste, lecture texte, répondre, répondre à tous, filtres, fil de discussion ✅. Archiver, déplacer (boîte de réception, archives, corbeille), supprimer (= corbeille, jamais définitif) ✅ : déplacement IMAP d'abord, base ensuite, annulation depuis la notification, vérifié contre Dovecot. Transférer ✅ (rédaction pré-remplie, message cité). Pièces jointes ✅ : liste et téléchargement seulement (jamais affichées, type forcé, `nosniff`), envoi jusqu'à 5 Mo, vérifié contre Dovecot et le SMTP de test. Brouillons enregistrés ✅ (dossier Drafts du serveur, rouvrables, remplacés à chaque enregistrement ; texte seul, sans pièces jointes). Manquent : vidage de la corbeille, affichage HTML isolé. |
| F-17 | C | ✅ | Trois styles (Moderne, Rétro, Néon), cinq palettes + accent libre, quatre modes, trois densités ; WCAG AA vérifié par test sur chaque combinaison. |
| F-18 | S | 🟡 | Autocomplétion sur une liste de démonstration. LDAP non implémenté, champs configurables absents de Réglages. |
| F-19 | S | 🟡 | Signature fixe. Pas de signatures par identité ni de modèles de message. |

### 4.3 Agenda
| ID | P | État | Remarques |
|---|---|---|---|
| F-20 | M | ✅ | CalDAV lecture/création (récurrences, écriture serveur d'abord). Testé contre des réponses simulées, **jamais contre un vrai serveur**. Google : ❌ (v2). Un événement existant ne se modifie ni ne se supprime. |
| F-21 | S | ❌ | Détection de conflits d'horaire. |
| F-22 | S | ✅ | Créneaux libres proposés depuis une demande de disponibilités (libre/occupé seulement). |
| F-23 | C | ❌ | Tâches de préparation générées par un événement. |
| F-24 | S | 🟡 | Sources avec drapeau « perso » ; une seule couleur de source dans la grille, pas de bascule par calendrier ni d'écriture dans l'agenda choisi. |
| F-25 | S | ❌ | Invitations (envoyer, accepter, refuser, iCalendar reçu). Seul le type de notification existe. |
| F-26 | C | ❌ | Partage d'agenda (libre/occupé) avec l'équipe. |

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
| F-54 | M | 🟡 | Voir F-12 : jours ouvrés ✅, choix par compte ❌. |
| F-55 | S | ✅ | Aucune relance automatique ; « ne plus relancer ». |
| F-56 | M | ✅ | Échéance propre, alerte et tri par retard. |
| F-57 | S | 🟡 | Un seul responsable ✅ ; pas d'écran d'assignation ni de transfert. |
| F-58 | S | ✅ | Journal non modifiable. |
| F-59 | M | 🟡 | Vue « Mes suivis » avec tuiles de situation ✅ ; filtres par compte, projet et étiquette ❌. |
| F-60 | S | ✅ | Périmètre « Équipe » avec état et ancienneté (dans la page Suivis, pas d'écran dédié). |
| F-61 | M | 🟡 | Récaps ✅ ; pas de ligne dédiée dans la to-do. |
| F-62 | S | 🟡 | Repère, échéance, relance rédigée, synthèse du jour ✅ ; pas de résumé de l'historique d'une demande. |
| F-63 | M | 🟡 | Entrée groupée, mode Relances, plafond quotidien, jamais d'envoi groupé ✅ ; regroupement des relances au même destinataire ❌. |
| F-64 | S | ✅ | Carte « Suggéré par l'IA », champs modifiables, rien sans clic ; refus non mémorisé. |
| F-65 | M | ✅ | Réponse reçue → « à traiter » + alerte, jamais de clôture automatique. |
| F-66 | S | 🟡 | Règle de création automatique côté API (alias) ; pas d'écran de gestion des règles. |
| F-67 | S | ✅ | Sans responsable : nouveau / pris par X / traité par X ; reprise, libération. |
| F-68 | S | ✅ | Avertissement « X s'en occupe », indicateur « X rédige », notification des autres membres. |
| F-69 | S | ✅ | Notes internes ; IA coupée par défaut sur une boîte partagée. |
| F-70 | M | ✅ | Priorités classées avec raison, tableau, filtres par type et par situation, Mes suivis / Équipe, synthèse d'Oscar, activité récente. |
| F-71 | M | ✅ | Prochain RDV (en cours ou suivant, ensuite, journée terminée), agenda perso optionnel. |
| F-72 | M | ✅ | Accueil allégé, trois états dont premier lancement en quatre étapes. |
| F-73 | M | 🟡 | Destinataires, Cc/Cci, objet, signature, suivi précoché avec délai, date d'alerte annoncée ✅. Manque la suggestion de tâche/date à valider à l'envoi et l'annuaire LDAP. |
| F-74 | S | 🟡 | Créneaux libres cochables, brouillon modifiable avec ton, rien sans clic ✅. Pose en provisoire dans l'agenda ❌ ; bascule « agenda perso » seulement dans Réglages. |

### 4.7 Notifications, connexions, journal
| ID | P | État | Remarques |
|---|---|---|---|
| F-75 | M | 🟡 | Dans l'application (regroupées, dédoublonnées) ✅. Notification du bureau (Tauri) et e-mail ❌ ; pas de choix de canal par type. |
| F-76 | M | ✅ | Réponse reçue / relance due en tête des priorités avec le motif. |
| F-77 | M | ✅ | Barre d'état : messagerie, agendas, IA avec mode ; détail et « Réessayer ». |
| F-78 | M | 🟡 | Journal exportable, filtre par compte, cartes de connexion. Pas de filtre par niveau (le journal n'a pas de gravité). |
| F-79 | M | ✅ | Messages clairs en français avec action ; alerte seulement après 3 échecs, une fois par jour. |
| F-80 | S | ❌ | Hors ligne et file d'envoi. |

## 2. Exigences non fonctionnelles

| ID | État | Remarques |
|---|---|---|
| N-01 | 🟡 | axe-core automatisé sur chaque écran, clavier, focus, réduction des animations. Pas de test lecteur d'écran réel. |
| N-02 | 🟡 | Zones live, sans vol de focus ; vérification à l'oreille manquante. |
| N-03 | 🟡 | Annulation des actions courantes ✅. Capture rapide : champ sur l'accueil ; pas de raccourci global. |
| N-04 | 🟡 | Identifiants chiffrés (libsodium), clé hors base ✅. HTTPS non contrôlé ici ; trousseau système (bureau) ❌. |
| N-05 | 🟡 | CAS testé sur des réponses simulées, **jamais contre le CAS réel**. Comptes locaux ✅ : inscription (désactivable, `APP_REGISTRATION`), connexion avec essais limités et message identique que le compte existe ou non, mot de passe oublié (lien à usage unique d'une heure, réponse neutre), changement de mot de passe (autres appareils déconnectés). Sessions révocables ✅ ; TOTP/passkey ❌. |
| N-06 | 🟡 | Export JSON de mes données et suppression définitive du compte (cascade) ✅, depuis Réglages > Mon compte. Pas de durées de conservation réglables ni de registre des traitements. Le journal des appels IA ne contient aucun contenu. |
| N-07 | ❌ | Performances non mesurées (100 000 mails). |
| N-08 | ✅ | Aucun récap ni alerte en double (unicité testée). |
| N-09 | ❌ | Pas de cache hors ligne. |
| N-10 | ✅ | fr/en, aucune chaîne en dur, clés vérifiées par test. |
| N-11 | 🟡 | Dev en une commande ✅, CI écrite (jamais exécutée sur GitHub). Image de production et Compose de déploiement ❌. Licence AGPL annoncée, fichier de licence à vérifier. |
| N-12 | 🟡 | Interface moderne, accessible, raccourcis Gmail. Gestes webmail courants présents ; vidage de la corbeille et HTML isolé manquent (F-16). |

## 3. Interface (section 10)

| ID | P | État | Remarques |
|---|---|---|---|
| U-01 | M | ✅ | Barre du bas à cinq boutons (Suivis au centre) sur mobile, rail latéral au bureau avec « Suivis » mis en avant. |
| U-02 | S | ❌ | Balayage entre vues. |
| U-03 | M | 🟡 | Un seul code adaptatif ✅ ; PWA installable ❌. |
| U-04 | M | 🟡 | Chaque bloc de l'accueil ouvre sa vue ; retour par bouton seulement, pas par geste. |
| U-05 | M | 🟡 | Alternatives clavier et pointeur simple partout où il y a un geste ; balayage absent donc rien à doubler. |
| U-06 | S | 🟡 | Bloc « Oscar » de la synthèse avec recommandations ✅ ; échange conversationnel ❌ (second temps prévu). |
| U-07 | M | ✅ | Résumé d'Oscar avec lien vers le récap en tête de l'accueil. |
| U-08 | M | ✅ | Icône + libellé partout, jamais la couleur seule ; contrastes vérifiés. |
| U-09 | S | ✅ | Trois styles, cinq palettes, accent libre, modes clair/sombre/contraste. |
| U-10 | S | ❌ | Ordre de repli des panneaux paramétrable. |
| U-11 | S | ✅ | Raccourcis alignés sur Gmail, désactivables. |

## 4. Écrans prévus (section 10.2)

| Écran | État | Remarques |
|---|---|---|
| Aujourd'hui | ✅ | |
| Réception | 🟡 | Voir F-16 (corbeille, HTML). |
| Mes suivis | ✅ | |
| Tâches | ✅ | Listes + détail comme la maquette. |
| Réglages | 🟡 | Section « Mon compte » ✅. Manquent : champs LDAP, règles de création automatique de suivis, délai par compte, canaux de notification par type. |
| Agenda | 🟡 | Grille ✅ ; invitations, bascule par calendrier, panneau « événement sélectionné », échéances de la semaine ❌. |
| Équipe | 🟡 | Périmètre « Équipe » de la page Suivis ; pas d'écran dédié. |
| Écrire | 🟡 | Voir F-73. |
| Récapitulatif | ✅ | |
| État et notifications | 🟡 | Barre d'état, notifications, journal ✅ ; choix des canaux ❌. |
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
| DesktopAgenda / MobAgenda | partiel | Reste : bascule de calendriers, invitation reçue à accepter, panneau de l'événement sélectionné, échéances de la semaine. |
| DesktopNotifs | non comparé | Reste : « Comment être prévenu » (canal par type). |
| DesktopReglages | non comparé | Voir tableau des écrans (LDAP, règles, délai par compte). |
| MobTaches, Suivis (mobile), Relance | partiel | Relance : brouillon, envoyer, reporter, ne plus relancer présents. Mise en page mobile contrôlée seulement par les tests de gabarit. |

## 6. Architecture et garde-fous IA (sections 3 et 5)

| Point | État | Remarques |
|---|---|---|
| Symfony + API Platform, PostgreSQL, Messenger/Scheduler | ✅ | Tests sur SQLite et PostgreSQL. |
| Webklex, Symfony Mailer, sabre/vobject | ✅ | |
| Reka UI, Pinia, Vue Router, vue-i18n | ✅ | FullCalendar pour l'agenda. |
| Mercure (temps réel) | ❌ | Interrogation du serveur chaque minute. |
| FrankenPHP | ❌ | `php -S` en développement ; pas d'image de production. |
| Cache hors ligne (service worker, IndexedDB) | ❌ | |
| Tauri 2 | ❌ | Non commencé. |
| Playwright | ❌ | Remplacé par `scripts/e2e.py` (HTTP + IMAP réels). |
| Affichage HTML isolé (iframe sandbox) | ❌ | Texte brut seulement : aucun contenu actif ne s'exécute. |
| Identifiants chiffrés, clé hors base | ✅ | |
| Texte d'un mail = donnée, jamais une consigne | ✅ | Test dédié sur un mail hostile. |
| Agenda : libre/occupé seulement vers l'IA | ✅ | Testé ; agenda perso seulement si activé. |
| Journal des appels IA sans contenu | ✅ | |
| IA interdite par compte | ✅ | Par dossier ❌. |
| Boîte partagée : IA seulement activée par un administrateur | ✅ | Coupée par défaut. |
| IA de l'université par défaut, externe désactivée | ✅ | Mode visible dans la barre d'état. |
| Fournisseurs Ollama et cloud | ❌ | L'interface `AiProvider` les attend ; seul le fournisseur à règles existe. |
| Sortie JSON validée par schéma | 🟡 | Sorties construites par le code local ; à ajouter avec un vrai modèle. |

## 7. Ce qui reste à faire, par ordre de valeur

1. **Webmail (F-16)** : vidage de la corbeille, HTML isolé, pièces jointes dans les brouillons.
2. **Fournisseur d'IA réel** derrière `AiProvider` (Ollama d'abord), sorties validées par schéma.
3. **Réglages manquants** : LDAP, règles de création de suivis, délai par compte, jours ouvrés, canaux de notification par type.
4. **Agenda** : invitations (F-25), bascule par calendrier, panneau de détail, conflits (F-21), vrai serveur CalDAV de test, Google.
5. **Tauri** (dans le MVP du cahier), puis PWA et hors ligne.
6. **Production** : image, Compose, documentation d'installation, test du CAS réel, RGPD (export, suppression, conservation).
7. **Suivi** : tâche liée à un suivi (F-36), filtres par compte/projet/étiquette (F-59), regroupement des relances au même destinataire.
