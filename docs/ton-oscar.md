# Le ton d'Oscar

Oscar est le majordome numérique de l'application (« Votre majordome numérique. », baseline de marque). Il suit, alerte, organise
et propose ; il ne décide ni n'envoie jamais rien seul. Cette charte est celle du cahier des charges (§5), rendue **exécutable** :
chaque phrase d'Oscar est vérifiée par test (`web/src/test/oscar.test.ts`).

## Règles

1. **Phrases courtes** : 18 mots au plus par phrase, 3 phrases au plus.
2. **Tutoiement** dans l'application (jamais de « vous », « votre », « veuillez »). La baseline de marque est la seule exception.
3. **Jamais de reproche** : pas de « tu as oublié », « tu aurais dû », « malheureusement », « tu n'as pas… ».
4. **Humour léger, qui disparaît dès qu'il y a une erreur** : un message d'erreur est d'abord clair, sans trait d'esprit ni point d'exclamation.
5. **Toujours une action proposée** : une question qui invite à choisir (« tu la relis ? ») ou un verbe d'action (« réessaie », « vérifie »).
   Seules les phrases purement informatives (bonjour, « plus de rendez-vous », « c'est clos ») en sont dispensées.
6. **Identifiable** : tout ce qu'Oscar propose porte son nom, son visage (nœud papillon) et la pastille « Suggéré par l'IA ».
7. **Pas de jargon technique** : jamais un code, une trace ou « null ».

## Où vivent les phrases

- Catalogue : `web/src/i18n/fr.ts` et `en.ts`, clé `oscar.<situation>.neutral|light`. `light` ajoute une pointe d'humour,
  `neutral` est la version sobre. L'utilisateur choisit dans Réglages > Oscar (« Ton d'Oscar »). Une situation d'erreur n'a que `neutral`.
- Situations : `web/src/oscar/situations.ts`.
- Règles vérifiées : `web/src/oscar/rules.ts`.
- Dans le code : **jamais** de phrase d'Oscar écrite dans un écran. On passe par `useOscar().say(situation, params)` ou par le
  composant `OscarSays` (`situation`, `params`, `suggestion`), qui applique le ton choisi et force le ton sobre en cas d'erreur.
- Le récap envoyé par e-mail (`api/src/Recap/RecapText.php`) reprend la même formule d'introduction.

## Phrases validées (exemples du cahier des charges)

| Situation | Ton léger | Ton sobre |
|---|---|---|
| Relance due | « {who} n'a pas donné signe de vie depuis {n} jours. J'ai préparé une relance, tu la relis ? » | « {who} n'a pas répondu depuis {n} jours. J'ai préparé une relance, tu la relis ? » |
| Journée finie | « Plus de rendez-vous aujourd'hui. Le plateau est à toi. » | « Plus de rendez-vous aujourd'hui. » |
| Disponibilités | « {who} demande tes disponibilités. J'ai trouvé {n} créneaux, tu choisis ? » | idem |
| Envoi échoué | : (pas d'humour en cas d'erreur) | « Le message n'est pas parti et rien n'a été créé. Réessaie dans un instant. » |

## Ajouter une phrase

1. Ajouter la situation dans `SITUATIONS` (et dans `ERROR_SITUATIONS` ou `INFO_SITUATIONS` si elle en relève).
2. Écrire `oscar.<situation>.neutral` (et `light` si pertinent) en français ET en anglais.
3. L'utiliser avec `OscarSays` ou `say()`.
4. `npm test` : le catalogue entier est revalidé ; une phrase qui enfreint la charte fait échouer la CI.
