# Installer Oscar

Oscar se déploie avec Docker Compose : une base PostgreSQL, l'application (HTTPS, API, front) et un worker (synchronisation des boîtes et agendas, récaps, relances).

> **État** : l'image a été construite et essayée de bout en bout (base, application, worker, assistant d'installation, connexion, administration) sur un Docker local. Pas encore essayée sur un serveur public avec HTTPS réel, CAS ni LDAP réels : voir « Ce qui reste à faire avant un vrai usage ».

## Prérequis

- Un serveur Linux avec Docker (24 ou plus) et le plugin Compose.
- Un nom de domaine qui pointe vers le serveur, et les ports **80 et 443** ouverts : Caddy obtient et renouvelle le certificat HTTPS tout seul.
- Un serveur SMTP pour les courriels d'Oscar (réinitialisation de mot de passe, notifications par e-mail).
- Facultatif : l'adresse du CAS de l'établissement, et celle de l'annuaire LDAP.

## Installation

```bash
git clone https://github.com/Dannebicque/oscar.git && cd oscar
cp docker/prod/env.example .env.prod
```

Remplis `.env.prod` (chaque variable est commentée). Les trois secrets se génèrent ainsi :

```bash
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'                 # APP_SECRET
php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;' # APP_VAULT_KEY
openssl rand -hex 24                                              # POSTGRES_PASSWORD
```

Puis :

```bash
docker compose -f compose.prod.yaml --env-file .env.prod up -d --build
docker compose -f compose.prod.yaml logs -f app
```

Les migrations s'appliquent au démarrage du service `app`. Ouvre `https://<ton domaine>`.

## Premier lancement : l'assistant d'installation

Au premier démarrage, le service `app` affiche un **code d'installation** dans ses journaux :

```bash
docker compose -f compose.prod.yaml logs app | grep -A2 "code d’installation"
```

Ouvre l'adresse de ton instance : l'assistant s'ouvre tout seul tant qu'aucun administrateur n'existe. Il demande le code, crée le compte **administrateur de l'instance**, puis règle le nom, l'ouverture des inscriptions et une adresse d'aide. Le code sert une seule fois ; cinq essais ratés bloquent l'assistant pendant une heure.

L'administrateur règle ensuite tout depuis **Administration** (menu de gauche) : nom, liens légaux, inscriptions, taille maximale des pièces jointes par défaut, autorisation de l'IA externe. Il gère l'outil, **jamais le contenu** : aucun mail, agenda ni tâche d'un utilisateur n'est lisible depuis cette page.

Sans interface (installation automatisée, dépannage) :

```bash
docker compose -f compose.prod.yaml exec app php bin/console app:admin:create ada@exemple.fr --name="Ada"
```

Elle crée l'administrateur, ou promeut un compte existant. `SETUP_TOKEN=…` dans `.env.prod` impose un code d'installation choisi à l'avance.

## Connexion des utilisateurs

| Besoin | Réglage |
|---|---|
| Connexion par le CAS de l'établissement | `CAS_BASE_URL` (le bouton n'apparaît que s'il est renseigné) |
| Comptes locaux (adresse + mot de passe) | Réglage « Création de comptes » de l'assistant puis d'**Administration** (`APP_REGISTRATION` ne donne que la valeur de départ) |
| Mot de passe oublié | `MAILER_DSN` et `MAILER_FROM` : sans eux, le lien n'est envoyé à personne |
| Annuaire des destinataires | `LDAP_URL`, `LDAP_BASE_DN`, compte de service en lecture (`LDAP_BIND_DN`, `LDAP_BIND_PASSWORD`) et attributs |

Chaque utilisateur connecte ensuite sa messagerie dans l'assistant (`/bienvenue`). En production, les adresses privées ou réservées sont refusées pour les serveurs de messagerie et les services d'IA (anti-SSRF).

## Sauvegarde

À sauvegarder **ensemble mais séparément** :

1. la base : `docker compose -f compose.prod.yaml exec db pg_dump -U oscar oscar > oscar-$(date +%F).sql` ;
2. **`APP_VAULT_KEY`** : sans elle, les mots de passe de messagerie enregistrés ne peuvent plus être déchiffrés (les utilisateurs devraient les ressaisir). Ne la mets jamais dans le même endroit que la base.

Restauration : `docker compose -f compose.prod.yaml exec -T db psql -U oscar oscar < oscar-AAAA-MM-JJ.sql`.

## Mise à jour

```bash
git pull
docker compose -f compose.prod.yaml --env-file .env.prod up -d --build
```

## Vérifier l'installation

- `docker compose -f compose.prod.yaml ps` : `db`, `app` et `worker` en cours d'exécution.
- `curl -s https://<domaine>/api/auth/config` répond un petit JSON.
- Crée un compte (ou connecte-toi par le CAS), connecte une messagerie : la barre d'état doit passer au vert après la première synchronisation (le worker la lance toutes les deux minutes).
- `docker compose -f compose.prod.yaml logs worker` : pas d'erreur répétée.

## Liste de contrôle de sécurité

- [ ] `.env.prod` hors du dépôt et lisible par root seulement (`chmod 600`).
- [ ] Création de comptes **fermée** (Administration) si l'instance est réservée à l'établissement.
- [ ] `CORS_ALLOW_ORIGIN` limité à l'adresse publique du site.
- [ ] Sauvegarde de la base **et** de `APP_VAULT_KEY`, restauration essayée une fois.
- [ ] Pare-feu : seuls 80 et 443 ouverts ; PostgreSQL n'est pas exposé (il ne l'est pas dans ce Compose).
- [ ] `MAILER_DSN` en TLS.

## Ce qui reste à faire avant un vrai usage

- Essayer l'image sur un serveur public (HTTPS réel avec un nom de domaine), pas seulement en local.
- Écran Administration : il ne contient pour l'instant que les réglages globaux ; la liste des utilisateurs, l'état de santé et le journal d'audit sont à venir.
- Essayer le CAS de l'établissement et le LDAP réels.
- Journaux centralisés et supervision (le journal d'erreurs d'Oscar est dans l'application, pas dans un outil externe).
