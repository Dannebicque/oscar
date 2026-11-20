#!/usr/bin/env bash
# Lance TOUS les tests d'Oscar d'un coup et dit ce qui passe :
#   1. API : PHPUnit sur SQLite, puis sur PostgreSQL, dont l'intégration IMAP réelle (Dovecot)
#   2. Front : types, tests (dont axe-core), build
#   3. Parcours de bout en bout dans la pile réelle (API + IMAP + SMTP de test) : scripts/e2e.py
# Démarre l'infrastructure si besoin et la laisse comme elle était. Option : --keep pour la laisser tourner.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
KEEP=0; [ "${1:-}" = "--keep" ] && KEEP=1
PG='postgresql://oscar:oscar-dev@127.0.0.1:5433/oscar_test?serverVersion=17&charset=utf8'
declare -a NAMES RESULTS
run() { # nom commande...
  local name="$1"; shift
  printf '\n\033[1m▶ %s\033[0m\n' "$name"
  if "$@"; then RESULTS+=("✅"); else RESULTS+=("❌"); fi
  NAMES+=("$name")
}

WAS_UP=0; [ -f .dev/api.pid ] && kill -0 "$(cat .dev/api.pid)" 2>/dev/null && WAS_UP=1
if [ "$WAS_UP" = 0 ]; then
  printf '\033[1m→ Démarrage de la pile (scripts/dev-up.sh)\033[0m\n'
  scripts/dev-up.sh > .dev/dev-up.out 2>&1 || { cat .dev/dev-up.out; echo "La pile n'a pas démarré."; exit 2; }
fi
(cd api && DATABASE_URL="postgresql://oscar:oscar-dev@127.0.0.1:5433/oscar?serverVersion=17&charset=utf8" php bin/console doctrine:database:create --env=test --if-not-exists >/dev/null 2>&1)

run "API · PHPUnit (SQLite en mémoire)"              bash -c 'cd api && php bin/phpunit'
run "API · PHPUnit (PostgreSQL)"                      bash -c "cd api && DATABASE_URL='$PG' php bin/phpunit"
run "API · intégration IMAP réelle (ne doit pas être ignorée)" bash -c 'cd api && php bin/phpunit tests/Mail/DovecotIntegrationTest.php --fail-on-skipped'
run "API · conteneur et schéma PostgreSQL"            bash -c "cd api && php bin/console lint:container && DATABASE_URL='postgresql://oscar:oscar-dev@127.0.0.1:5433/oscar?serverVersion=17&charset=utf8' php bin/console doctrine:schema:validate"
run "IMAP · scénarios Dovecot (smoke)"                python3 docker/dovecot/smoke.py
run "Front · types"                                   bash -c 'cd web && npx vue-tsc --noEmit'
run "Front · tests (domaine, flux, HTTP, axe-core)"   bash -c 'cd web && npm test'
run "Front · build"                                   bash -c 'cd web && npm run build >/dev/null'
run "Bout en bout · pile réelle"                      python3 scripts/e2e.py

if [ "$WAS_UP" = 0 ] && [ "$KEEP" = 0 ]; then scripts/dev-down.sh >/dev/null 2>&1; fi

printf '\n\033[1m──── Bilan ────\033[0m\n'
FAIL=0
for i in "${!NAMES[@]}"; do printf '%s %s\n' "${RESULTS[$i]}" "${NAMES[$i]}"; [ "${RESULTS[$i]}" = "❌" ] && FAIL=1; done
[ "$FAIL" = 0 ] && printf '\nTout passe.\n' || printf '\nDes tests échouent : relis les sorties ci-dessus.\n'
exit "$FAIL"
