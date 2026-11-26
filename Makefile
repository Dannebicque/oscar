# Raccourcis de développement. « make » liste les commandes.
.DEFAULT_GOAL := help
.PHONY: help dev stop restart status logs migrate sync seed-history test test-api test-web test-all build site site-preview

help: ## Cette aide
	@grep -hE '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  make %-14s %s\n", $$1, $$2}'

dev: ## Tout lancer : PostgreSQL, IMAP de test, migrations, API, worker, SMTP de test, front
	@scripts/dev-up.sh

stop: ## Tout arrêter (les données de dev sont jetables)
	@scripts/dev-down.sh

restart: ## Relancer l'API, le worker et le front (la base reste)
	@scripts/dev-up.sh

status: ## Ce qui tourne, et sur quels ports
	@cat .dev/ports 2>/dev/null || echo "Application arrêtée."
	@for p in api worker web smtp-sink; do if [ -f .dev/$$p.pid ] && kill -0 $$(cat .dev/$$p.pid) 2>/dev/null; then echo "  $$p : en marche"; else echo "  $$p : arrêté"; fi; done
	@docker compose -f docker/dev/compose.yaml ps

logs: ## Suivre les journaux (Ctrl+C pour sortir)
	@tail -n 20 -f .dev/api.log .dev/worker.log .dev/web.log .dev/smtp-sink.log

migrate: ## Appliquer les migrations à la base de dev
	cd api && php bin/console doctrine:migrations:migrate -n

sync: ## Synchroniser les messageries maintenant
	cd api && php bin/console app:mail:sync

seed-history: ## Ajouter un historique de demandes (pour essayer le bilan)
	cd api && php bin/console app:suivis:seed --history

test: test-api test-web ## Tests de l'API et du front

test-api: ## PHPUnit (SQLite en mémoire)
	cd api && php bin/phpunit

test-web: ## Types et tests du front
	cd web && npx vue-tsc --noEmit && npm test

test-all: ## Tout : SQLite, PostgreSQL, IMAP réel, front, parcours de bout en bout
	@scripts/test-all.sh

build: ## Construire le front (comme l'image de production)
	cd web && npm run build

site: ## Construire le site de présentation avec la démo
	cd site && npm run build

site-preview: site ## Construire puis servir le site sur http://localhost:4173
	cd site && npm run preview
