# Oscar, application de bureau (Tauri 2)

Le bureau **n'ajoute pas de logique métier** : il charge le même front (`web/`) que le navigateur. Il apporte seulement ce qu'un navigateur ne sait pas faire (cahier des charges §3) :

| Besoin | Réalisation |
|---|---|
| Secrets dans le trousseau du système (N-04) | Le jeton de session est lu et écrit par les commandes `token_get`, `token_set`, `token_delete` (crate `keyring` : macOS Keychain, Windows Credential Manager, Secret Service). Jamais dans `localStorage` sur le bureau. |
| Notifications natives (F-75) | Le front écoute ce que le serveur a remis sur le canal « bureau » (Réglages > Alertes) et appelle le plugin de notification, une fois par notification. |
| Capture rapide (N-03) | Raccourci global `Cmd/Ctrl + Maj + Espace` (et `q` dans l'application) : la fenêtre se met au premier plan et ouvre la saisie d'une tâche. |
| Icône de barre système | Menu : Ouvrir Oscar, Capture rapide, Quitter. Un clic ouvre la fenêtre. |
| Une seule instance | Relancer l'application ramène la fenêtre existante. |

Côté web, le pont (`web/src/desktop/bridge.ts`) est **inerte** sans Tauri : le même code tourne dans le navigateur.

## État

Le socle est écrit et le pont côté web est testé (`web/src/test/desktop.test.ts`). Le code Rust **compile** (`cargo check`, `cargo clippy` et `cargo build` sans erreur sur macOS, Rust 1.100, Tauri 2.12) et l'application **se lance avec le front** (essai à la main sur macOS). Le trousseau, la notification native, le raccourci global et la barre système restent à dérouler avec la liste de contrôle, ainsi que Windows et Linux. Les critères de validation du cahier (installation, notifications, trousseau, accessibilité de la webview) restent à vérifier sur chaque système, en particulier **VoiceOver, NVDA et Orca** : la webview dépend de l'OS (comparatif §3).

## Lancer

Prérequis : [Rust](https://rustup.rs) et les [dépendances système de Tauri 2](https://v2.tauri.app/start/prerequisites/) (sous Linux : `libwebkit2gtk-4.1-dev libsecret-1-dev libdbus-1-dev`).

```bash
scripts/dev-up.sh            # l'API et le front (http://localhost:5173)
cd desktop && npm install
npm run dev                  # ouvre la fenêtre Oscar sur le front de développement
npm run build                # installateur (dmg, msi, deb/AppImage) ; construit d'abord web/dist
```

Icônes provisoires : `python3 scripts/make-desktop-icons.py`. Pour des icônes définitives : `npm run icons` (à partir de `src-tauri/icons/source.png`, 1024 px).

## Reste à faire

- Compiler et corriger (premier passage).
- Signature et notarisation des installateurs (macOS, Windows), mises à jour automatiques (`tauri-plugin-updater`).
- Adresse de l'API de production dans la CSP (`tauri.conf.json`) et dans `VITE_API_URL`.
- Tests sur les trois systèmes, dont lecteurs d'écran.
