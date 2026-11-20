# API (Symfony 7 + API Platform)

À initialiser (Composer n'était pas joignable depuis l'environnement de génération) :

```bash
composer create-project symfony/skeleton:"7.3.*" api-tmp
# puis déplacer le contenu dans ce dossier
composer require api symfony/orm-pack symfony/security-bundle symfony/mailer
composer require --dev symfony/maker-bundle
```

## Contrat attendu par le front (`web/src/api/suivis.ts`)

- `GET /api/suivis` : liste des suivis `{ id, subject, contact, state, dueAt, suggestedByAi }`
  avec `state` ∈ `toHandle | relanceDue | late | waiting | done`.
- `POST /api/suivis/{id}/close` : clôt un suivi (passe à `done`).

Règles métier (cahier des charges v2) : un suivi par fil de conversation, une réponse reçue passe
à « à traiter » sans clôture automatique, les relances sont seulement suggérées, jamais envoyées seules.
