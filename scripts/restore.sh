#!/usr/bin/env bash
# Restaure une sauvegarde faite par scripts/backup.sh. DESTRUCTIF : la base actuelle est remplacée.
#   scripts/restore.sh backups/oscar-2026-11-23_120000.dump
# Variables : COMPOSE_FILE (défaut compose.prod.yaml), ENV_FILE (défaut .env.prod), ASSUME_YES=1 pour ne pas demander confirmation.
set -euo pipefail
cd "$(dirname "$0")/.."
FILE="${1:?Indique le fichier de sauvegarde (voir scripts/backup.sh).}"
[ -s "$FILE" ] || { echo "Fichier introuvable ou vide : $FILE" >&2; exit 1; }
COMPOSE_FILE="${COMPOSE_FILE:-compose.prod.yaml}"
ENV_FILE="${ENV_FILE:-.env.prod}"
dc() { docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" "$@"; }

if [ "${ASSUME_YES:-0}" != "1" ]; then
  printf "Cela REMPLACE la base actuelle par %s. Tape « oui » pour continuer : " "$FILE"
  read -r answer
  [ "$answer" = "oui" ] || { echo "Annulé, rien n'a changé."; exit 1; }
fi

echo "→ Arrêt de l'application et du worker"
dc stop app worker
echo "→ Restauration"
dc exec -T db psql -U oscar -d postgres -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS oscar WITH (FORCE)" -c "CREATE DATABASE oscar OWNER oscar"
dc exec -T db pg_restore -U oscar -d oscar --no-owner --exit-on-error < "$FILE"
echo "→ Redémarrage (les migrations manquantes s'appliquent toutes seules)"
dc up -d app worker
echo "Restauration terminée."
