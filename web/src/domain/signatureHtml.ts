/**
 * Assainissement de démonstration (mode hors ligne) : même idée que le serveur, qui seul fait foi à l'envoi.
 * Liste blanche de balises, jamais d'attribut d'événement, de lien javascript ni d'image hors https.
 */
const ALLOWED = new Set(['a', 'b', 'strong', 'i', 'em', 'u', 's', 'small', 'sub', 'sup', 'br', 'hr', 'p', 'div', 'span', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'img', 'h1', 'h2', 'h3', 'h4', 'blockquote'])
const DROP = new Set(['script', 'style', 'iframe', 'frame', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'noscript', 'title', 'head', 'template', 'audio', 'video', 'canvas'])
const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

function walk(node: Node): string {
  let out = ''
  node.childNodes.forEach((c) => {
    if (c.nodeType === 3) out += esc(c.nodeValue ?? '')
    else if (c instanceof Element) {
      const tag = c.tagName.toLowerCase()
      if (DROP.has(tag)) return
      if (!ALLOWED.has(tag)) { out += walk(c); return }
      let attrs = ''
      const href = c.getAttribute('href')?.trim()
      if (tag === 'a' && href && /^(https?:\/\/|mailto:|tel:)\S*$/i.test(href)) attrs += ` href="${esc(href)}" rel="noopener noreferrer"`
      const src = c.getAttribute('src')?.trim()
      if (tag === 'img') { if (!src || !/^https:\/\/\S*$/i.test(src)) return; attrs += ` src="${esc(src)}"`; const alt = c.getAttribute('alt'); if (alt) attrs += ` alt="${esc(alt)}"` }
      if (['br', 'hr', 'img'].includes(tag)) { out += `<${tag}${attrs}>`; return }
      out += `<${tag}${attrs}>${walk(c)}</${tag}>`
    }
  })
  return out
}
export function sanitizeSignature(html: string): string {
  const doc = new DOMParser().parseFromString(`<div id="r">${html}</div>`, 'text/html')
  return walk(doc.getElementById('r') ?? doc.body).trim()
}
export function signatureText(safeHtml: string): string {
  const doc = new DOMParser().parseFromString(`<div id="r">${safeHtml.replace(/<br\s*\/?>/gi, '\n').replace(/<\/(p|div|li|tr|h\d)>/gi, '</$1>\n')}</div>`, 'text/html')
  const text = (doc.getElementById('r')?.textContent ?? '').split('\n').map((l) => l.trim()).join('\n').replace(/\n{3,}/g, '\n\n').trim()
  return text ? `-- \n${text}` : ''
}
