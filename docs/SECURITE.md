# Sécurité et vie privée

Ce document dit, sans enjolivement, **ce qu'Oscar garde, où, ce que l'IA voit, comment l'accès est protégé, et ce qui n'est pas (encore) fait**. Il est écrit pour un administrateur, un délégué à la protection des données ou un utilisateur curieux. Installation : [INSTALL.md](INSTALL.md) ; administration : [ADMIN.md](ADMIN.md).

> Oscar est un logiciel libre (AGPL) que **tu héberges toi-même**. L'auteur n'a accès à aucune donnée de ton instance. Ce document décrit le logiciel, pas une garantie juridique : fais-le relire par ton délégué à la protection des données avant un usage réel.

## Ce qu'Oscar garde (dans sa base PostgreSQL)

| Donnée | Détail |
|---|---|
| Comptes | Nom, adresse, **mot de passe haché** (jamais en clair), rôle, état. Sessions : jetons opaques, **hachés**, qui expirent au bout de 12 heures et se révoquent. |
| Messageries connectées | Serveurs et identifiant ; le **mot de passe de messagerie est chiffré** (libsodium) avec `APP_VAULT_KEY`, qui n'est pas dans la base. |
| Mails | Les **en-têtes** seulement : expéditeur, destinataires, objet (tronqué à 500 caractères), date, identifiants de fil. **Jamais le corps ni les pièces jointes** : ils sont lus à la demande sur ton serveur de messagerie, jamais gardés. Les mails eux-mêmes restent chez le fournisseur de messagerie. |
| Mails programmés | Le message **complet (texte et pièces jointes)** attend d'être envoyé, puis la ligne est effacée. Un envoi en échec reste jusqu'à ce que tu le rouvres ou l'annules. |
| Suivis, tâches, notes | Ce que tu y écris (objet, contact, dates, notes internes des boîtes partagées). |
| Agenda | Événements synchronisés : titre, lieu, horaires, participants. |
| Réglages, identités, signatures, modèles | Ce que tu y saisis. Une signature HTML est **nettoyée** avant d'être gardée. |
| Notifications, journal de connexion | Messages d'état, sans contenu de mail. |
| Journal des appels d'IA | Une **ligne résumée** par appel (fournisseur, usage, résumé court), jamais le contenu du mail. |
| Journal d'audit de l'instance | Actions d'administration ; les **noms** des réglages changés, jamais leurs valeurs. |
| Cache | Lien de mot de passe (haché, valable une heure ou sept jours pour une invitation), compteurs d'essais de connexion. |

**Sur l'appareil de l'utilisateur** : l'application garde en mémoire locale (navigateur) les données déjà chargées pour la consultation hors ligne, et les messages écrits hors ligne en attente d'envoi (pièces jointes comprises), effacés à la déconnexion. Sur l'application de bureau, la session est dans le **trousseau du système**, pas dans un fichier.

Les **sauvegardes** de la base contiennent tout ce qui précède : traite-les comme des données sensibles (accès restreint, chiffrement du support).

## Ce que voit l'administrateur de l'instance

**Rien du contenu.** Pas de mail, pas d'agenda, pas de tâche, pas de suivi, pas même leur nombre. Il ne peut pas se connecter en tant que quelqu'un, et le lien de mot de passe qu'il déclenche part **par e-mail à la personne seulement**. Ses actions sont inscrites dans le journal d'audit. Tout cela est vérifié par des tests automatiques.

Il ne protège pas contre la personne qui a un **accès au serveur** (base de données, sauvegardes, machine) : celle-là peut, techniquement, tout lire de ce qui est gardé en base. Sépare les rôles : l'administrateur d'Oscar n'a pas besoin de l'accès au serveur, et inversement.

## Ce que voit l'IA

- **Par défaut : l'IA de l'établissement** (règles locales ; aucun service externe).
- **IA externe, si l'administrateur l'autorise et si l'utilisateur la configure** (un fournisseur compatible OpenAI ou Anthropic, avec sa propre clé) : c'est **le seul cas où du contenu quitte ton infrastructure**. Sont envoyés l'objet et le texte du mail analysé (tronqué à 8 000 caractères) ou le brouillon demandé. **Jamais** : les pièces jointes, rien de l'agenda (titres, lieux, participants : les créneaux libres sont calculés localement), les autres mails. La clé du fournisseur est chiffrée comme les mots de passe de messagerie.
- L'administrateur peut **interdire l'IA externe** pour toute l'instance (Administration > Réglages). Un utilisateur peut aussi couper l'IA, ou l'interdire pour une messagerie précise.
- Le texte d'un mail est une **donnée**, jamais une consigne (protection contre l'injection de consignes). La sortie de l'IA est validée champ par champ : une valeur invalide est ignorée. Une panne du fournisseur ne bloque rien.
- **L'IA ne décide ni n'envoie jamais** : tout ce qu'elle propose attend un clic, avec la pastille « Suggéré par l'IA ».
- Chaque appel est consigné (résumé) et consultable.

## Protections en place

| Sujet | Mesure |
|---|---|
| Mots de passe | Au moins 10 caractères, hachés ; cinq essais de connexion par quart d'heure ; message identique que le compte existe ou non ; sessions fermées après un changement de mot de passe. |
| Accès réseau sortant | Les serveurs de messagerie, d'agenda et d'IA saisis par un utilisateur sont contrôlés : adresses privées, locales ou réservées refusées en production (anti-SSRF). |
| Contenu des mails | **Le HTML reçu n'est jamais affiché tel quel** : l'API le reconstruit à partir d'une liste blanche, puis il s'affiche dans un cadre sans script. Les images distantes ne se chargent que si tu le demandes (l'expéditeur peut alors savoir que tu lis, et voit ton adresse réseau) ; les pixels espions ne se chargent jamais. Les pièces jointes ne s'ouvrent pas dans l'application, elles se téléchargent (sauf les images insérées dans le corps du message). |
| Signatures HTML | Reconstruites à partir d'une liste blanche (pas de script, de formulaire, de cadre, de lien javascript ; images en https seulement). L'aperçu est dans un cadre isolé. |
| En-têtes HTTP | Politique de contenu stricte (`default-src 'self'`), interdiction d'être mis en cadre, `nosniff`, politique de référent. |
| Données | Chaque utilisateur ne voit que les siennes (isolation vérifiée par test) ; une boîte partagée ne montre rien des mails personnels de ses membres. |
| Boîtes partagées | Une personne n'entre dans une boîte qu'après avoir accepté l'invitation ; avant, rien de ses mails n'est partagé et elle ne voit aucune demande. Une adresse de compte ou de messagerie personnelle ne peut pas devenir une boîte partagée. |
| Secrets | Hors de l'image Docker et du dépôt (`.env.prod`) ; connexion de développement impossible en production. |
| Administration | Réservée au rôle administrateur ; dernier administrateur protégé ; journal d'audit. |
| Envoi | **Aucun mail ne part sans un clic** ; aucune clôture ni relance automatique ; **jamais de suppression définitive de mail** (corbeille). |

## Ce qui n'est pas (encore) fait

- **Pas d'audit de sécurité indépendant.** Le code est ouvert et testé, mais personne d'extérieur ne l'a audité.
- **Pas de double authentification** pour les comptes locaux ; les comptes CAS profitent de celle de l'établissement.
- **Pas de chiffrement de la base elle-même** (hormis les mots de passe et clés ci-dessus). Chiffre le disque du serveur et les sauvegardes.
- Les **objets de mails** sont gardés en clair dans la base (ils servent au suivi).
- **Session de 12 heures** non réglable pour l'instant.
- **Journalisation centralisée et alertes** : l'onglet Santé est un état ponctuel, pas une supervision.
- L'image n'a pas été éprouvée sur un serveur public ; CAS et LDAP n'ont pas été essayés sur des serveurs réels.

## RGPD, en pratique

- **Responsable de traitement** : l'organisation qui héberge l'instance (pas l'auteur du logiciel).
- **Droit d'accès et portabilité** : l'utilisateur exporte ses données depuis Réglages > Mon compte (aucun mot de passe n'y figure).
- **Effacement** : l'utilisateur supprime son compte lui-même, ou l'administrateur le fait (Administration > Comptes) ; tout ce que l'instance garde pour lui disparaît. Les mails restent chez son fournisseur de messagerie.
- **Minimisation** : pas de corps de mail ni de pièces jointes gardés ; l'IA externe est désactivable et désactivée tant qu'elle n'est pas configurée.
- **Sous-traitants** : aucun par défaut. Un **fournisseur d'IA externe** devient un sous-traitant dès qu'un utilisateur en configure un : à encadrer par ton organisation.
- **Durées de conservation** : pas de purge automatique pour l'instant (hors envoi des mails programmés et expiration des sessions et liens). À décider selon ta politique.
- **Registre des traitements, analyse d'impact** : à rédiger par ton organisation ; ce document en fournit la matière.

## Signaler une vulnérabilité

Voir [SECURITY.md](../SECURITY.md).
