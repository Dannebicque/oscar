#!/usr/bin/env bash
# Lance tout Oscar en local : PostgreSQL, IMAP de test, API, worker (planificateur), SMTP de test et front.
# Journaux dans .dev/. Arrêt : scripts/dev-down.sh. Prérequis : docker, php 8.4+, composer, node, python3.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"
cd "$ROOT"
mkdir -p .dev
DB_URL='postgresql://oscar:oscar-dev@127.0.0.1:5433/oscar?serverVersion=17&charset=utf8'

# Relancer ne doit pas empiler les processus : on arrête d'abord ceux d'une session précédente.
scripts/dev-down.sh --processes

# Premier port libre à partir de celui demandé : un autre projet occupe souvent 8000 ou 5173.
free_port() {
  local p="$1"
  while lsof -nP -iTCP:"$p" -sTCP:LISTEN >/dev/null 2>&1; do p=$((p + 1)); done
  echo "$p"
}
API_PORT="$(free_port "${API_PORT:-8000}")"
WEB_PORT="$(free_port "${WEB_PORT:-5173}")"

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
FRONT_URL="http://localhost:$WEB_PORT"
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
start api "$ROOT/api" php -d post_max_size=40M -d upload_max_filesize=40M -d memory_limit=256M -d max_execution_time=120 -S "127.0.0.1:$API_PORT" -t public
start worker "$ROOT/api" php bin/console messenger:consume scheduler_default -vv
[ -d web/node_modules ] || (cd web && npm ci --silent)
printf 'VITE_API_URL=http://127.0.0.1:%s\n' "$API_PORT" > web/.env.local
start web "$ROOT/web" "$ROOT/web/node_modules/.bin/vite" --port "$WEB_PORT" --strictPort
printf 'API_PORT=%s\nWEB_PORT=%s\n' "$API_PORT" "$WEB_PORT" > .dev/ports
sleep 2
cat <<MSG

Oscar tourne :
  Front        http://localhost:$WEB_PORT   (connexion de dev : david, claire, marc ou lea)
  API          http://127.0.0.1:$API_PORT/api
  IMAP de test 127.0.0.1:1143          SMTP de test 127.0.0.1:1025 (livre dans les boîtes)
  Journaux     .dev/*.log               Arrêt : make stop
MSG
