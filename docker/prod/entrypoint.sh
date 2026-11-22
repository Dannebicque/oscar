#!/bin/sh
# Démarrage de l'image Oscar : attend la base, applique les migrations (une seule fois, par le service « app »), puis lance la commande.
set -e
cd /app

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "→ Migrations"
  i=0
  until php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
      echo "La base de données ne répond pas : vérifie DATABASE_URL." >&2
      php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
      exit 1
    fi
    sleep 2
  done
  # Première installation : le code à saisir dans l'assistant s'affiche dans les journaux (rien à faire une fois l'instance installée).
  php bin/console app:setup:token || true
fi

exec "$@"
