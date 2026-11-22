# Site de présentation

Site statique (fr `/`, en `/en/`) généré sans dépendance par `build.mjs`, avec la **démo interactive** de l'application sous `/demo/` (données fictives, rien n'est envoyé). Pas de cookie, pas de mesure d'audience.

```bash
cd site
npm run build:fast   # pages seulement
npm run build        # pages + démo (construit web/ avec VITE_USE_MOCK)
npm run build:prod   # idem, refuse de construire si une mention légale est « À COMPLÉTER »
npm run preview      # http://localhost:4173
```

Prérequis : `npm ci` dans `web/` (la démo et les polices viennent de là). Résultat dans `site/dist/`, à déposer sur n'importe quel hébergement statique.

## Réglages (variables d'environnement, voir `config.mjs`)

| Variable | Rôle |
|---|---|
| `SITE_NAME` | Nom du produit (**pas définitif** : jamais écrit en dur ailleurs) |
| `SITE_URL` | Adresse publique : liens canoniques, plan du site |
| `SITE_BASE` | Chemin de service (`/` par défaut ; `/oscar/` pour un sous-dossier) |
| `SITE_CONTACT` | Adresse de contact (sinon la page renvoie vers le dépôt) |
| `SITE_LEGAL_PUBLISHER`, `_ADDRESS`, `_DIRECTOR`, `_HOST`, `_FORM`, `_REGISTRATION` | Mentions légales : **à renseigner par l'éditeur**, rien n'est inventé |
| `SITE_DEMO=0` | Pas de démo |

Textes : `content/fr.mjs` et `content/en.mjs` (mêmes clés ; `{name}` = nom du produit). Chaque affirmation doit correspondre à une fonction réelle (`docs/CONFORMITE.md`). Les offres hébergée et d'accompagnement n'affichent aucun tarif tant qu'il n'est pas arrêté.
