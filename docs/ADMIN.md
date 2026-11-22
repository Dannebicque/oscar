# Guide de l'administrateur

Ce guide s'adresse à la personne qui gère une instance d'Oscar : l'installation est décrite dans [INSTALL.md](INSTALL.md), la sécurité dans [SECURITE.md](SECURITE.md).

## Ce que tu es, et ce que tu n'es pas

**L'administrateur de l'instance gère l'outil, jamais le contenu.** Tu peux régler Oscar, créer, inviter, désactiver et supprimer des comptes, surveiller la santé du service. Tu ne peux **pas** :

- lire les mails, les agendas, les tâches ou les suivis d'un utilisateur, ni même en connaître le nombre ;
- te connecter « en tant que » quelqu'un d'autre ;
- recevoir un lien de mot de passe à la place de l'utilisateur : le lien part **par e-mail à la personne seulement**. Tu ne peux donc jamais prendre la main sur un compte.

Cette limite est volontaire : elle est ce qui permet à tes utilisateurs de te faire confiance, et elle est vérifiée par des tests automatiques. Dis-la-leur.

## Devenir administrateur, en nommer d'autres

- Le premier administrateur est créé par l'[assistant d'installation](INSTALL.md#premier-lancement--lassistant-dinstallation).
- Pour en nommer d'autres : **Administration > Comptes > Rendre administrateur**.
- **Prévois toujours au moins deux administrateurs.** Le dernier administrateur actif ne peut être ni désactivé, ni supprimé, ni rétrogradé : ce garde-fou t'évite de te verrouiller, mais il ne remplace pas une deuxième personne.
- Sans interface (dépannage) : `docker compose -f compose.prod.yaml exec app php bin/console app:admin:create ada@exemple.fr`.

## Les quatre onglets d'Administration

### Réglages

| Réglage | Effet |
|---|---|
| Nom de l'instance | Affiché sur la page de connexion et dans le titre des pages. |
| Adresse d'aide | Montrée aux utilisateurs qui ont un souci. |
| Mentions légales, confidentialité | Adresses en `https://` affichées aux utilisateurs. |
| Création de comptes | **Fermée** : tu invites chaque personne (ou elle arrive par le CAS). **Ouverte** : chacun peut créer un compte local. |
| Pièces jointes : taille maximale par défaut | De 1 à 25 Mo. Chaque compte de messagerie peut fixer la sienne, qui prime. |
| Autoriser l'IA externe | Désactivé : personne ne peut envoyer ses mails à un service d'IA externe ; seule l'IA de l'établissement reste disponible. |

Les changements s'appliquent tout de suite, sans redémarrage. Les secrets (clés, mots de passe) ne passent jamais par cette page : ils restent dans `.env.prod`.

### Comptes

Pour chaque personne : nom, adresse, rôle, état, type de compte (local ou connexion par l'établissement), dernière connexion, nombre de messageries connectées.

| Action | Ce qui se passe |
|---|---|
| **Inviter** | Un compte local est créé sans mot de passe. La personne reçoit un e-mail avec un lien (valable 7 jours) pour choisir le sien. Si le courriel ne part pas, Oscar te le dit : vérifie l'onglet Santé. |
| **Désactiver** | La personne est déconnectée partout, tout de suite ; elle ne peut plus se connecter ; ses synchronisations s'arrêtent. **Rien n'est supprimé** : **Réactiver** remet tout en l'état. |
| **Envoyer un lien de mot de passe** | Comptes locaux seulement. Le lien est envoyé à la personne, valable une heure. |
| **Rendre administrateur / retirer l'administration** | Voir plus haut. |
| **Supprimer le compte** | Définitif : le compte et toutes ses données dans Oscar (suivis, tâches, réglages, comptes connectés) sont effacés. Il faut recopier l'adresse du compte pour confirmer. Les mails restent sur le serveur de messagerie de la personne. |

### Santé

Un état à l'instant T, avec une icône **et** un texte (jamais la couleur seule) :

| Contrôle | Ce qu'il dit |
|---|---|
| Base de données | Elle répond, et en combien de millisecondes. |
| Tâches de fond | Le worker bat chaque minute. S'il est « à l'arrêt », **rien ne se synchronise et rien ne part** (récaps, relances, rappels, mails programmés). |
| Synchronisation des messageries | Combien de comptes sont en échec répété. |
| Mails programmés | Combien n'ont pas pu partir. |
| Espace disque | Libre en pourcentage ; sous 20 %, surveille, sous 10 %, agis. |
| Courriels d'Oscar | `MAILER_DSN` est-il configuré ? Sinon, invitations et notifications par e-mail ne partent pas. |
| Adresse publique, mode de fonctionnement, code d'installation | https, mode production, code d'installation effacé. |

Ce n'est pas un outil d'alerte : il faut y aller. Pour une vraie supervision, branche un outil externe sur le service.

### Journal

Qui a changé quoi : réglages modifiés (les **noms** des réglages, jamais leurs valeurs), invitations, comptes désactivés, réactivés ou supprimés, droits d'administration, liens de mot de passe envoyés, installation. Jamais de contenu d'utilisateur.

## Questions qu'on te posera

**« Mon administrateur peut-il lire mes mails ? »** Non. Il ne voit ni les mails, ni les agendas, ni les tâches, ni leur nombre. Il ne peut pas se faire passer pour toi.

**« Que devient mon compte si je pars ? »** Tu peux exporter tes données (Réglages > Mon compte) puis supprimer ton compte, ou l'administrateur le désactive (réversible) puis le supprime (définitif). Tes mails restent sur ton serveur de messagerie.

**« Où sont mes données ? »** Voir [SECURITE.md](SECURITE.md), section « Ce qu'Oscar garde ».

**Droit à l'effacement (RGPD)** : supprime le compte (Comptes > Supprimer). **Droit d'accès et portabilité** : la personne exporte elle-même ses données depuis ses Réglages ; l'administrateur n'a volontairement pas accès au contenu.

## Au quotidien

- Regarde **Santé** après chaque mise à jour et de temps en temps.
- Garde ta **sauvegarde** à jour et essaie une restauration une fois : [INSTALL.md](INSTALL.md#sauvegarde-et-restauration).
- Avant de fermer ou d'ouvrir les inscriptions, préviens tes utilisateurs.
