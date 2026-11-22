/**
 * Réglages du site de présentation. Tout ce qui dépend de ta marque, de ton domaine ou de ton entreprise est ICI :
 * le nom n'est pas définitif, il ne doit donc être écrit nulle part ailleurs.
 *
 * Les valeurs « À COMPLÉTER » font échouer `npm run build:prod` : on ne publie pas un site avec des mentions légales à trous.
 */
const TODO = 'À COMPLÉTER'
const env = process.env

export const config = {
  /** Nom du produit tel qu'affiché (les textes disent {name}). */
  name: env.SITE_NAME || 'Oscar',
  /** Adresse publique du site, sans barre finale (liens canoniques, plan du site). Vide en essai local. */
  url: (env.SITE_URL || '').replace(/\/$/, ''),
  /** Chemin sous lequel le site est servi (« / » si le site a son propre domaine). */
  base: env.SITE_BASE || '/',
  repo: env.SITE_REPO || 'https://github.com/Dannebicque/oscar',
  /** Adresse de contact affichée. Sans elle, la page Contact renvoie vers le dépôt. */
  contactEmail: env.SITE_CONTACT || '',
  /** Mentions légales : à renseigner par l'éditeur du site (obligation légale, ne rien inventer). */
  legal: {
    publisher: env.SITE_LEGAL_PUBLISHER || TODO, // nom ou raison sociale de l'éditeur
    form: env.SITE_LEGAL_FORM || '', // statut juridique, si société
    address: env.SITE_LEGAL_ADDRESS || TODO,
    director: env.SITE_LEGAL_DIRECTOR || TODO, // directeur de la publication
    host: env.SITE_LEGAL_HOST || TODO, // hébergeur du site : nom, adresse, téléphone
    registration: env.SITE_LEGAL_REGISTRATION || '', // SIRET / RCS, si applicable
  },
  /** La démo publique est construite sous /demo/ par `npm run build` (voir README). */
  demo: env.SITE_DEMO !== '0',
  locales: ['fr', 'en'],
}

/** Valeurs encore à compléter (pour le contrôle de publication). */
export function todos() {
  return Object.entries(config.legal).filter(([, v]) => v === TODO).map(([k]) => `legal.${k}`)
}
