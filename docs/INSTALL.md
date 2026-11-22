# Installer Oscar

Oscar se déploie avec Docker Compose : une base PostgreSQL, l'application (HTTPS, API, interface) et un worker (synchronisation des boîtes et des agendas, récaps, relances, rappels de rendez-vous, mails programmés).

> **État** : l'image a été construite et essayée de bout en bout (base, application, worker, assistant d'installation, connexion, administration, sauvegarde et restauration) sur un Docker local. Elle n'a **pas encore** été essayée sur un serveur public avec un vrai nom de domaine, ni avec un CAS ou un LDAP réels : voir « Avant un vrai usage ».

Autres guides : [administration](ADMIN.md) · [utilisateur](UTILISATEUR.md) · [sécurité et vie privée](SECURITE.md).

## Ce qu'il te faut

| Besoin | Détail |
|---|---|
| Serveur | Linux avec Docker 24 ou plus et le plugin Compose. Mesuré au repos : environ 120 Mo de mémoire pour les trois services. Prévois 1 Go de mémoire et quelques Go de disque pour démarrer sereinement (ordre de grandeur à confirmer selon le nombre d'utilisateurs). |
| Nom de domaine | Un nom qui pointe vers le serveur (enregistrement DNS `A`, et `AAAA` si IPv6). |
| Ports | **80 et 443** ouverts : Caddy obtient et renouvelle tout seul le certificat HTTPS. Rien d'autre n'est exposé (PostgreSQL ne l'est pas). |
| Courriels | Un serveur SMTP pour les courriels d'Oscar : invitations, mots de passe oubliés, notifications. Sans lui, l'application marche, mais ces courriels ne partent pas (l'onglet Santé le signale). |
| Facultatif | L'adresse du CAS de l'établissement, celle de l'annuaire LDAP. |

## Installation

```bash
git clone https://github.com/Dannebicque/oscar.git && cd oscar
cp docker/prod/env.example .env.prod
chmod 600 .env.prod
```

Remplis `.env.prod` (chaque variable y est commentée). Les trois secrets se génèrent ainsi :

```bash
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'                 # APP_SECRET
php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;' # APP_VAULT_KEY
openssl rand -hex 24                                              # POSTGRES_PASSWORD
```

Sans PHP sur ta machine : `openssl rand -hex 16` pour `APP_SECRET`, et `echo "base64:$(openssl rand -base64 32)"` pour `APP_VAULT_KEY`.

Puis :

```bash
docker compose -f compose.prod.yaml --env-file .env.prod up -d --build
docker compose -f compose.prod.yaml logs -f app
```

La première construction prend quelques minutes. Les migrations de la base s'appliquent au démarrage du service `app`. Ouvre ensuite `https://<ton domaine>`.

> Pour un essai sans nom de domaine, mets `SERVER_NAME=:80` dans `.env.prod` : Oscar est alors servi en HTTP simple sur le port 80. **N'utilise pas cela pour de vrais utilisateurs.**

## Premier lancement : l'assistant d'installation

Au premier démarrage, le service `app` affiche un **code d'installation** dans ses journaux :

```bash
docker compose -f compose.prod.yaml logs app | grep -A2 "code d’installation"
```

Ouvre l'adresse de ton instance : l'assistant s'ouvre tout seul tant qu'aucun administrateur n'existe. Il demande le code, crée le compte **administrateur de l'instance**, puis règle le nom, l'ouverture des inscriptions et une adresse d'aide. Le code sert une seule fois ; cinq essais ratés bloquent l'assistant pendant une heure.

Sans interface (installation automatisée, dépannage) :

```bash
docker compose -f compose.prod.yaml exec app php bin/console app:admin:create ada@exemple.fr --name="Ada"
```

La commande crée l'administrateur, ou promeut un compte existant. `SETUP_TOKEN=…` dans `.env.prod` impose un code d'installation choisi à l'avance.

La suite se passe dans **Administration** (menu de gauche) : voir le [guide de l'administrateur](ADMIN.md).

## Variables de `.env.prod`

| Variable | Rôle | Obligatoire |
|---|---|---|
| `SERVER_NAME` | Nom de domaine public (HTTPS automatique). `:80` pour un essai sans HTTPS. | oui |
| `POSTGRES_PASSWORD` | Mot de passe de la base (interne, non exposée). | oui |
| `APP_SECRET` | Secret de l'application (signatures). | oui |
| `APP_VAULT_KEY` | Chiffre les mots de passe de messagerie. **À sauvegarder à part** : sans elle, ils ne se déchiffrent plus. | oui |
| `FRONT_URL`, `DEFAULT_URI`, `CORS_ALLOW_ORIGIN` | Adresse publique, utilisée dans les liens des courriels et pour limiter les origines autorisées. | oui |
| `MAILER_DSN`, `MAILER_FROM` | Envoi des courriels d'Oscar (par exemple `smtp://utilisateur:motdepasse@smtp.exemple.fr:587`). | recommandé |
| `CAS_BASE_URL` | Connexion par le CAS. Le bouton n'apparaît que s'il est renseigné. | non |
| `LDAP_URL`, `LDAP_BASE_DN`, `LDAP_BIND_DN`, `LDAP_BIND_PASSWORD`, `LDAP_ATTR_*`, `LDAP_FILTER` | Annuaire des destinataires. | non |
| `APP_REGISTRATION` | Valeur de départ de la création de comptes (`open` ou `closed`). Ensuite, c'est le réglage d'**Administration** qui compte. | non |
| `SETUP_TOKEN` | Code d'installation choisi à l'avance (sinon généré au premier démarrage). | non |
| `APP_VERSION` | Numéro de version affiché dans l'onglet Santé. | non |

Le nom de l'instance, les liens légaux, l'ouverture des inscriptions, la taille maximale des pièces jointes et l'autorisation de l'IA externe ne se règlent **pas** ici : ils se changent dans Administration, sans redémarrage.

## Connexion des utilisateurs

| Besoin | Réglage |
|---|---|
| Connexion par le CAS de l'établissement | `CAS_BASE_URL` |
| Comptes locaux (adresse et mot de passe) | « Création de comptes » dans Administration. Fermée : tu invites chaque personne. |
| Mot de passe oublié, invitations | `MAILER_DSN` et `MAILER_FROM` |
| Annuaire des destinataires | Les variables `LDAP_*` |

Chaque utilisateur connecte ensuite sa propre messagerie dans l'assistant (`/bienvenue`). En production, les adresses privées ou réservées sont refusées pour les serveurs de messagerie et les services d'IA (protection contre l'accès au réseau interne, dite SSRF).

## Sauvegarde et restauration

À sauvegarder **ensemble mais séparément** :

1. **La base**, avec le script fourni :

   ```bash
   scripts/backup.sh               # dans ./backups, garde les 14 plus récentes
   KEEP=30 scripts/backup.sh       # en garde 30
   ```

   Le script vérifie que le fichier est lisible avant de le garder. Planifie-le (par exemple une fois par nuit avec `cron`) et copie `./backups` **hors du serveur**.

2. **`APP_VAULT_KEY`** (dans `.env.prod`), dans un autre endroit que la base : gestionnaire de mots de passe ou coffre. Sans elle, les mots de passe de messagerie enregistrés ne se déchiffrent plus et chaque utilisateur doit les ressaisir.

**Restaurer** (destructif : la base actuelle est remplacée, une confirmation est demandée) :

```bash
scripts/restore.sh backups/oscar-2026-11-23_120000.dump
```

Le script arrête l'application et le worker, remplace la base, puis les relance ; les migrations manquantes s'appliquent toutes seules. **Essaie une restauration une fois**, sur une machine de test, avant d'en avoir besoin.

Les mails eux-mêmes ne sont **pas** dans la sauvegarde d'Oscar : ils restent sur les serveurs de messagerie des utilisateurs.

## Mise à jour

```bash
scripts/backup.sh                                                  # toujours sauvegarder d'abord
git pull
docker compose -f compose.prod.yaml --env-file .env.prod up -d --build
docker compose -f compose.prod.yaml logs --tail=50 app            # migrations appliquées, pas d'erreur
```

Puis ouvre **Administration > Santé** : tout doit être « OK ». **Retour arrière** : `git checkout <version précédente>`, reconstruis, puis, si une migration a changé la base, `scripts/restore.sh` avec la sauvegarde faite juste avant.

## Vérifier l'installation

- `docker compose -f compose.prod.yaml ps` : `db`, `app` et `worker` en cours d'exécution.
- `curl -s https://<domaine>/api/instance` répond un petit JSON (nom, inscriptions).
- **Administration > Santé** : base, tâches de fond, courriels et https en « OK ». Le worker bat chaque minute : s'il est « à l'arrêt », rien ne se synchronise ni ne part.
- Connecte une messagerie : la barre d'état passe au vert après la première synchronisation (toutes les deux minutes).

## Dépannage

| Symptôme | Piste |
|---|---|
| La page ne s'ouvre pas en HTTPS | Le DNS pointe-t-il vers le serveur ? Les ports 80 et 443 sont-ils ouverts ? Regarde `docker compose -f compose.prod.yaml logs app` : Caddy y explique ce qu'il n'arrive pas à obtenir. |
| L'assistant d'installation ne s'ouvre pas | Un administrateur existe déjà (l'assistant est alors fermé). Pour en créer un : `app:admin:create`. |
| « Code d'installation incorrect » | Relis-le dans les journaux (`logs app`). Cinq essais ratés bloquent une heure. |
| Aucune synchronisation, aucun récap | Santé > « Tâches de fond » : le service `worker` tourne-t-il ? `docker compose -f compose.prod.yaml logs worker`. |
| Les invitations ne partent pas | Santé > « Courriels d'Oscar » : `MAILER_DSN` est vide ou invalide. Le compte invité est créé quand même. |
| Les mots de passe de messagerie ne marchent plus après une restauration | `APP_VAULT_KEY` n'est pas celle d'origine : remets l'ancienne, sinon chaque utilisateur ressaisit ses mots de passe. |
| Le disque se remplit | Santé > « Espace disque ». Les sauvegardes s'accumulent dans `./backups` (`KEEP`), les journaux Docker aussi. |

## Liste de contrôle de sécurité

- [ ] `.env.prod` hors du dépôt, lisible par son propriétaire seulement (`chmod 600`).
- [ ] Création de comptes **fermée** (Administration) si l'instance est réservée à un établissement ou à une équipe.
- [ ] `CORS_ALLOW_ORIGIN` limité à l'adresse publique.
- [ ] Sauvegarde de la base **et** de `APP_VAULT_KEY`, restauration essayée une fois.
- [ ] Pare-feu : seuls 80 et 443 ouverts.
- [ ] `MAILER_DSN` en TLS.
- [ ] Administration > Santé : tout en « OK » (https, courriels, mode production, code d'installation effacé).
- [ ] Au moins deux administrateurs, pour ne pas dépendre d'une seule personne.

## Avant un vrai usage

- Essayer l'image sur un serveur public (HTTPS réel, nom de domaine), pas seulement en local.
- Essayer le CAS de l'établissement et le LDAP réels.
- Journaux centralisés et supervision externe : l'onglet Santé est un état à l'instant T, pas un outil d'alerte.
