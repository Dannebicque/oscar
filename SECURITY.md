# Signaler une vulnérabilité

Merci de signaler un problème de sécurité **en privé** : ne publie ni détail ni preuve de concept dans un ticket public.

1. Sur GitHub, ouvre l'onglet **Security** du dépôt puis **Report a vulnerability** (signalement privé), si cette option est activée.
2. Sinon, ouvre un ticket public **sans aucun détail technique**, en demandant seulement un moyen de contact privé.

Dis-nous : la version ou le commit concerné, ce que tu as observé, comment le reproduire, et l'effet possible. Nous accusons réception dès que possible et te tenons informé(e) de la correction. Un correctif est publié avant toute divulgation détaillée.

## Ce qui nous intéresse en priorité

- Lecture ou modification des données d'un autre utilisateur (isolation entre comptes).
- Un administrateur d'instance qui accède au **contenu** d'un utilisateur (mails, agendas, tâches) ou prend la main sur un compte.
- Contournement de l'authentification, élévation de droits, accès à l'administration.
- Envoi, clôture ou suppression sans action de l'utilisateur.
- Injection (consignes dans un mail vers l'IA, HTML dans une signature, requêtes vers le réseau interne).
- Fuite de secrets (mots de passe de messagerie, clés).

Le modèle de sécurité, ce qui est protégé et ce qui ne l'est pas encore sont décrits dans [docs/SECURITE.md](docs/SECURITE.md).
