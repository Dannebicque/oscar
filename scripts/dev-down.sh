#!/usr/bin/env bash
# Arrête ce que scripts/dev-up.sh a lancé.
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
for p in web worker api smtp-sink; do
  [ -f ".dev/$p.pid" ] && kill "$(cat ".dev/$p.pid")" 2>/dev/null || true
  rm -f ".dev/$p.pid"
done
pkill -f "vite" 2>/dev/null || true
docker compose -f docker/dev/compose.yaml down
rm -f web/.env.local
echo "Arrêté."
