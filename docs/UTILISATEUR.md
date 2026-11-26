# Guide de l'utilisateur

Oscar est ton **majordome numérique** : il t'aide à ne rien laisser sans réponse. Son cœur, c'est le **suivi** des conversations. Il suggère, prépare et rappelle ; **il ne décide, n'envoie et ne clôt jamais rien à ta place**.

Pour l'installation et l'administration, voir [INSTALL.md](INSTALL.md) et [ADMIN.md](ADMIN.md).

## Premiers pas

1. **Connecte-toi** avec le compte de ton établissement, ou avec ton adresse et ton mot de passe. Si ton instance est fermée, ton administrateur t'envoie une invitation par e-mail : le lien te permet de choisir ton mot de passe.
2. **Connecte ta messagerie** dans l'assistant (adresse, mot de passe de messagerie). Oscar devine les serveurs d'après ton adresse et **les teste avant d'enregistrer** : rien n'est gardé si ça ne marche pas. Ton mot de passe de messagerie est chiffré.
3. Facultatif : connecte un agenda (CalDAV, Google).
4. Ouvre **Suivis** : c'est ton tableau de bord.

Ta messagerie reste ce qu'elle est : tes mails restent sur ton serveur, dans tes dossiers, lisibles depuis n'importe quel autre client.

## Le suivi, en trois idées

- **Un suivi par conversation.** Il naît quand tu écris un mail en cochant « Suivre la réponse », quand Oscar te suggère de suivre un mail reçu (tu valides, il ne crée rien tout seul), ou par une règle que tu as choisie.
- **Une réponse reçue** passe le suivi à « à traiter » : c'est à toi de décider s'il reste quelque chose à faire. **Rien n'est clos automatiquement.**
- **Une relance est une suggestion.** Oscar prépare un brouillon, tu le relis, tu l'envoies. Il y a un plafond quotidien pour ne pas te noyer.

Un suivi a un **sens** (à traiter / réponse attendue) et un **état** (nouveau, en cours, en attente, traité). « En retard », « relance due » et « réponse reçue » sont des signaux calculés, jamais des états que tu dois gérer.

### Les écrans de Suivis

- **Priorités** : ce qui demande ton attention d'abord, avec la raison.
- **Tableau** : tes suivis par état.
- **Bilan** : taux de réponse, délai médian, demandes qui attendent depuis longtemps, état par contact. Pour comprendre où se perdent les réponses ; il ne relance personne.
- **Relire les relances** : toutes les relances dues sur un écran, chacune avec son brouillon. Une relance ne part que si tu la relis et la coches « prête », ou avec son propre bouton « Envoyer ». Envoyer plusieurs d'un clic demande une confirmation qui nomme les destinataires. Plusieurs relances au même destinataire sont regroupées en un seul message (tu peux les séparer).

## Écrire un mail

- **De** : si tu as plusieurs identités (alias, fonction), choisis laquelle écrit. Elle est choisie d'office quand tu réponds à un mail adressé à l'un de tes alias ou à une boîte partagée. Les noms, adresses « Répondre à » et **signatures** (texte ou HTML) se règlent dans Réglages > Comptes et agendas.
- **Pièces jointes** : glisse tes fichiers n'importe où sur la page, ou « Parcourir… » pour en choisir plusieurs. La limite de ton compte et la place utilisée sont affichées en permanence (10 Mo par défaut, de 1 à 25 Mo selon ton fournisseur).
- **Suivre la réponse** : coché d'office quand le message attend visiblement une réponse ; modifiable. Le suivi n'est créé que si le message est réellement parti.
- **Annuler l'envoi** : après « Envoyer », le message attend quelques secondes (10 par défaut, réglable ou désactivable) ; tu peux l'annuler, il revient dans l'éditeur.
- **Programmer** : choisis demain matin, lundi matin, cet après-midi, ou une date. Le message part à l'heure choisie ; tu peux l'annuler ou le modifier depuis le dossier « Programmés » tant qu'il n'est pas parti.
- **Enregistrer le brouillon** garde le texte, les pièces jointes, les copies et l'identité, et les retrouve à la reprise.
- **Modèles** : des textes à insérer (Réglages), avec `{prenom}` et `{moi}`.

## Mails : dossiers, tri, recherche

- Les **dossiers et sous-dossiers** sont ceux de ton serveur de messagerie : « Mes dossiers » dans le rail, avec le bouton + pour en créer. Un dossier ne se supprime que vide.
- **Archiver**, **corbeille** (confirmation réglable, **jamais de suppression définitive**), **déplacer**, tout cela avec un bouton Annuler.
- **Traitement par lot** : « Sélectionner », coche des messages, puis archive, déplace ou mets à la corbeille d'un coup.
- **Recherche avancée** : « Filtres avancés », ou directement dans la barre : `de:claire`, `objet:devis`, `apres:2026-11-01`, `avant:2026-11-30`, `pj`, `suivi`, `nonlu`.
- **Raccourcis clavier** (désactivables dans Réglages) : `c` écrire, `r` répondre, `j`/`k` message suivant/précédent, `e` archiver, `#` ou Suppr corbeille, `s` suivre, `/` rechercher, `g` puis `i`/`s`/`t`/`a`/`h` pour aller aux mails, suivis, tâches, agenda, accueil, `?` pour l'aide.

## Boîtes partagées (contact@…)

Elles se gèrent dans **Réglages > Comptes et agendas**. Crée la boîte avec son adresse commune : tu en deviens administrateur, puis tu invites tes collègues (ils doivent avoir un compte). **Rien n'est partagé tant qu'ils n'ont pas accepté** l'invitation, dans Réglages ; ils peuvent la refuser. Chacun peut **quitter** une boîte ; ses propres mails restent chez lui. Une adresse personnelle ne peut pas servir de boîte partagée. Une boîte ne se supprime que si elle n'a encore aucune demande.

Quand une équipe partage une adresse, tous ses membres reçoivent chaque mail. **Personne n'est désigné responsable** : chaque demande est « nouvelle », « prise par X » ou « traitée par X », ce qui évite les doublons (« Claire s'en occupe »). Tu peux ajouter des notes internes, jamais envoyées. Tes mails personnels ne sont **jamais** visibles des autres membres. Tes réponses partent « De : contact@ » avec le nom et la signature de l'équipe.

## Agenda

Événements de tes agendas, rappel avant un rendez-vous (15 minutes par défaut, réglable) avec les derniers échanges de mails avec les participants. Invitations reçues : tu acceptes, refuses ou réponds « peut-être » d'un clic, et la réponse part à l'organisateur. Pour proposer des créneaux, Oscar ne lit que **libre/occupé**.

## L'IA

L'IA **suggère et prépare** : résumé d'un mail, brouillon de réponse, tâche ou rendez-vous repérés, créneaux libres, brouillon de relance. Tout ce qu'elle propose porte la pastille violette « Suggéré par l'IA » et attend ton clic.

- Par défaut, c'est l'IA **de ton établissement**. Tu peux en choisir une externe dans Réglages (si ton administrateur l'autorise), ou couper l'IA.
- Un mail n'est jamais pris pour une consigne : son texte est traité comme une donnée.
- Elle ne reçoit **jamais** les pièces jointes, ni les titres, lieux ou participants de ton agenda.
- Tu peux interdire l'IA pour un compte de messagerie précis (boîte confidentielle).

## Mode congés

Réglages > Relances et suivi : choisis un premier et un dernier jour. Pendant l'absence, **les relances et les récaps sont en pause** ; rien n'est fermé ni supprimé, tout revient à ton retour. En ouvrant un mail reçu, Oscar propose de **préparer ton message d'absence** : tu le relis et tu l'envoies, **jamais automatiquement**.

## Hors ligne, application de bureau

- L'application s'installe comme une application (PWA) et garde de quoi consulter ce qui est déjà chargé ; **les messages écrits hors ligne partent au retour du réseau**.
- Une application de bureau (Tauri) existe, avec notifications natives et un raccourci global pour capturer une tâche (`Ctrl/Cmd + Maj + Espace`).

## Ton compte, tes données

Réglages > **Mon compte** : changer ton mot de passe, **exporter toutes tes données** (un fichier, sans aucun mot de passe), **supprimer ton compte** et tout ce qu'Oscar garde pour toi. Tes mails restent sur ton serveur de messagerie.

## Accessibilité

Interface utilisable au clavier et au lecteur d'écran ; un état n'est **jamais** dit par la couleur seule (toujours une icône et un texte) ; cibles tactiles d'au moins 44 px ; contraste vérifié (WCAG AA) pour chaque style et chaque palette ; trois styles (Moderne, Rétro, Néon), thème clair, sombre ou contraste élevé, densité réglable, français et anglais.

## Le ton d'Oscar

Phrases courtes, tutoiement, jamais de reproche, une action toujours proposée, un peu d'humour qui disparaît dès qu'il y a une erreur. Tu peux choisir le ton « léger » ou « sobre » dans Réglages > Oscar.
