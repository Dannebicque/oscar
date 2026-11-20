# Serveur IMAP de test (Dovecot)

Les boîtes sont en mémoire (`tmpfs`) : jetables, rien n'est écrit sur disque. Après un `down` ou un redémarrage, relancer `seed.py`.

Pour développer et tester l'étape IMAP sans toucher à une vraie boîte. **Local uniquement** : écoute sur `127.0.0.1:1143`,
sans TLS, mots de passe publics. Ne jamais l'exposer ni y mettre de vrais mails.

```bash
docker compose -f docker/dev/compose.yaml up -d --wait dovecot
python3 docker/dovecot/seed.py          # charge les mails de test (idempotent, --reset pour repartir de zéro)
python3 docker/dovecot/smoke.py         # vérifie les scénarios ; code de sortie ≠ 0 si écart
python3 docker/dovecot/smtp-sink.py     # SMTP de test (127.0.0.1:1025) : livre dans les boîtes ci-dessous
```

## Comptes

| Utilisateur | Mot de passe | Rôle                                  |
|-------------|--------------|---------------------------------------|
| `david`     | `oscar-test` | utilisateur principal                 |
| `claire`    | `oscar-test` | membre de l'alias `contact@`          |
| `marc`      | `oscar-test` | membre de l'alias `contact@`          |
| `lea`       | `oscar-test` | membre de l'alias `contact@`          |

Serveur : `127.0.0.1`, port `1143`, IMAP sans chiffrement, identifiant seul (pas de `@domaine`).
Côté API, `php bin/console app:dev:setup` crée ces quatre utilisateurs, leurs comptes de messagerie (mot de passe chiffré) et l'alias `contact@` ; `scripts/dev-up.sh` fait tout.

## Scénarios couverts

| Cahier des charges | Mails de test |
|---|---|
| F-52 réponse détectée par `In-Reply-To` / `References` | « Devis des affiches » (envoyé) et la réponse de Marc |
| F-52 une réponse automatique ne compte pas | « Absence : Convention de stage » (`Auto-Submitted: auto-replied`) |
| F-11 tâche et date à extraire | « Planning des soutenances » (« avant mardi », « jeudi à 10h ») |
| F-44 alias : un message, quatre boîtes, un seul `Message-ID` | « Question d'inscription » reçu par les quatre comptes |

Les identifiants et mots de passe de ce dossier sont des valeurs de test ; en production les identifiants IMAP
sont chiffrés avec une clé hors base (§3 du cahier des charges).
