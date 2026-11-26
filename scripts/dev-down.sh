#!/usr/bin/env bash
# Arrête ce que scripts/dev-up.sh a lancé : seulement les processus de CE dépôt, jamais ceux d'un autre projet.
#   scripts/dev-down.sh               tout arrêter (processus et conteneurs)
#   scripts/dev-down.sh --processes   les processus seulement (PostgreSQL et Dovecot restent)
ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"
cd "$ROOT"

# Tue les processus correspondant au motif, à condition qu'ils tournent dans ce dépôt.
stop_mine() {
  local pid cwd
  for pid in $(pgrep -f "$1" 2>/dev/null); do
    cwd="$(lsof -a -d cwd -p "$pid" -Fn 2>/dev/null | sed -n 's/^n//p')"
    case "$cwd" in "$ROOT" | "$ROOT"/*) kill "$pid" 2>/dev/null || true ;; esac
  done
}

stop_mine 'node_modules/(\.bin/)?vite'
stop_mine 'messenger:consume scheduler_default'
stop_mine 'smtp-sink\.py'
stop_mine 'php .*-S 127\.0\.0\.1:[0-9]+'
rm -f .dev/web.pid .dev/worker.pid .dev/api.pid .dev/smtp-sink.pid .dev/ports

[ "${1:-}" = "--processes" ] && exit 0
docker compose -f docker/dev/compose.yaml down
rm -f web/.env.local
echo "Arrêté."
