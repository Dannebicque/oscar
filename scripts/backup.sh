#!/usr/bin/env bash
# Sauvegarde de la base d'Oscar (format PostgreSQL « custom », compressé) dans ./backups, avec rotation.
#   scripts/backup.sh                    # sauvegarde et garde les 14 plus récentes
#   KEEP=30 scripts/backup.sh            # en garde 30
# Variables : COMPOSE_FILE (défaut compose.prod.yaml), ENV_FILE (défaut .env.prod), BACKUP_DIR (défaut ./backups).
#
# ATTENTION : la base ne suffit pas. APP_VAULT_KEY (dans .env.prod) chiffre les mots de passe de messagerie : sans elle, ils ne se
# déchiffrent plus. Sauvegarde-la AILLEURS que la base (gestionnaire de mots de passe, coffre), jamais dans le même dossier.
set -euo pipefail
cd "$(dirname "$0")/.."
COMPOSE_FILE="${COMPOSE_FILE:-compose.prod.yaml}"
ENV_FILE="${ENV_FILE:-.env.prod}"
BACKUP_DIR="${BACKUP_DIR:-backups}"
KEEP="${KEEP:-14}"
dc() { docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" "$@"; }

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
FILE="$BACKUP_DIR/oscar-$(date +%Y-%m-%d_%H%M%S).dump"
dc exec -T db pg_dump -U oscar -Fc oscar > "$FILE.partial"
# Un fichier vide ou illisible n'est pas une sauvegarde : on le vérifie avant de le garder.
dc exec -T db pg_restore --list < "$FILE.partial" > /dev/null
mv "$FILE.partial" "$FILE"
chmod 600 "$FILE"
echo "Sauvegarde : $FILE ($(du -h "$FILE" | cut -f1))"

# Rotation : on ne garde que les $KEEP plus récentes.
ls -1t "$BACKUP_DIR"/oscar-*.dump 2>/dev/null | tail -n +"$((KEEP + 1))" | while read -r old; do rm -f -- "$old"; done
echo "Rappel : sauvegarde aussi APP_VAULT_KEY, séparément de ce fichier."
