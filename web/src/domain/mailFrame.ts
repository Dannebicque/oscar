/**
 * Document du cadre qui affiche un mail mis en forme (F-16). Le HTML arrive déjà reconstruit par l'API ; ceci est la seconde barrière :
 * rien ne se charge ni ne s'exécute, sauf les images portées par le message, et les images distantes seulement si on les a demandées.
 * Le cadre lui-même est ouvert sans `allow-scripts`.
 */
export const MAIL_SANDBOX = 'allow-same-origin allow-popups allow-popups-to-escape-sandbox'

export function mailDocument(html: string, remoteImages: boolean): string {
  const csp = `default-src 'none'; style-src 'unsafe-inline'; img-src data:${remoteImages ? ' https:' : ''}; base-uri 'none'; form-action 'none'`
  // Le courrier est écrit pour un fond clair : on le garde clair dans tous les thèmes (couleurs du système, pas de code en dur).
  const css = ':root{color-scheme:light}html,body{margin:0;background:Canvas;color:CanvasText}body{padding:16px;font:15px/1.5 system-ui,sans-serif;overflow-wrap:anywhere;display:flow-root}img{max-width:100%;height:auto}a{color:LinkText}pre{white-space:pre-wrap}@media (max-width:640px){body{padding:12px}table,div{max-width:100%!important}table[width],table[style*=width]{width:100%!important}td[width],th[width]{width:auto!important}}'
  return `<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="${csp}"><meta name="referrer" content="no-referrer"><style>${css}</style></head><body>${html}</body></html>`
}
