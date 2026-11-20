#!/usr/bin/env bash
# Lance tout Oscar en local : PostgreSQL, IMAP de test, API, worker (planificateur), SMTP de test et front.
# Journaux dans .dev/. Arrêt : scripts/dev-down.sh. Prérequis : docker, php 8.4+, composer, node, python3.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
mkdir -p .dev
DB_URL='postgresql://oscar:oscar-dev@127.0.0.1:5433/oscar?serverVersion=17&charset=utf8'

echo "→ Infrastructure (PostgreSQL, Dovecot)"
docker compose -f docker/dev/compose.yaml up -d --wait

echo "→ API"
cd api
[ -d vendor ] || composer install --no-interaction
[ -f .env ] || cp .env.example .env
grep -q '^APP_SECRET=.\+' .env || sed -i.bak "s|^APP_SECRET=.*|APP_SECRET=$(php -r 'echo bin2hex(random_bytes(16));')|" .env
grep -q '^APP_VAULT_KEY=.\+' .env || sed -i.bak "s|^APP_VAULT_KEY=.*|APP_VAULT_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));')|" .env
rm -f .env.bak
cat > .env.local <<ENV
DATABASE_URL="$DB_URL"
ENV
php bin/console doctrine:migrations:migrate -n -q
php bin/console app:dev:setup -q
cd "$ROOT"

# Lance un processus détaché : sa sortie va dans .dev/<nom>.log, son pid dans .dev/<nom>.pid.
start() { # nom répertoire commande...
  local name="$1" dir="$2"; shift 2
  ( cd "$dir" && exec "$@" ) > "$ROOT/.dev/$name.log" 2>&1 < /dev/null &
  echo $! > "$ROOT/.dev/$name.pid"
}

echo "→ Mails de test"
python3 docker/dovecot/seed.py >/dev/null
start smtp-sink "$ROOT" python3 docker/dovecot/smtp-sink.py
(cd api && php bin/console app:suivis:seed --user=david -q && php bin/console app:mail:sync >/dev/null 2>&1) || true

echo "→ API, worker, front"
start api "$ROOT/api" php -S 127.0.0.1:8000 -t public
start worker "$ROOT/api" php bin/console messenger:consume scheduler_default -vv
[ -d web/node_modules ] || (cd web && npm ci --silent)
printf 'VITE_API_URL=http://127.0.0.1:8000\n' > web/.env.local
start web "$ROOT/web" npm run dev
sleep 2
cat <<MSG

Oscar tourne :
  Front        http://localhost:5173   (connexion de dev : david, claire, marc ou lea)
  API          http://127.0.0.1:8000/api
  IMAP de test 127.0.0.1:1143          SMTP de test 127.0.0.1:1025 (livre dans les boîtes)
  Journaux     .dev/*.log               Arrêt : scripts/dev-down.sh
MSG
