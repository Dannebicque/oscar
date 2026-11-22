#!/usr/bin/env node
/**
 * Générateur du site de présentation. Sans dépendance : Node 20+ suffit (la démo utilise la chaîne de `web/`).
 *   node build.mjs           construit site/dist (avec la démo si SITE_DEMO≠0)
 *   node build.mjs --prod    refuse de construire tant qu'une mention légale est « À COMPLÉTER »
 *   node build.mjs --no-demo saute la démo (plus rapide)
 */
import { mkdirSync, writeFileSync, copyFileSync, rmSync, existsSync, readFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'
import { config, todos } from './config.mjs'
import fr from './content/fr.mjs'
import en from './content/en.mjs'

const here = dirname(fileURLToPath(import.meta.url))
const dist = join(here, 'dist')
const args = process.argv.slice(2)
const prod = args.includes('--prod')
const withDemo = config.demo && !args.includes('--no-demo')

const missing = todos()
if (missing.length) {
  const msg = `Mentions légales à compléter : ${missing.join(', ')} (variables SITE_LEGAL_*, voir README).`
  if (prod) { console.error('✗ ' + msg); process.exit(1) }
  console.warn('! ' + msg)
}

const L = { fr, en }
const base = config.base.endsWith('/') ? config.base : config.base + '/'
const prefix = (lang) => (lang === 'fr' ? base : base + 'en/')
const PAGES = ['', 'features/', 'privacy/', 'install/', 'offers/', 'contact/', 'legal/']
const KEY = { '': 'home', 'features/': 'features', 'privacy/': 'privacy', 'install/': 'selfhost', 'offers/': 'offers', 'contact/': 'contact', 'legal/': 'legal' }
const SLUG = { home: '', features: 'features/', privacy: 'privacy/', selfhost: 'install/', offers: 'offers/', contact: 'contact/', legal: 'legal/' }

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
const sub = (s) => String(s).replaceAll('{name}', config.name).replaceAll('{repo}', config.repo)
const t = (s) => esc(sub(s))

const ICONS = {
  follow: '<path d="M4 12l5 5L20 6"/><path d="M4 6h8" opacity=".5"/>',
  sparkles: '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
  lock: '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
  users: '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2a3 3 0 010 5.6M18 14.8c2 .6 3.5 2.3 3.5 5.2"/>',
  calendar: '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
  check: '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/>',
  edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
}
const icon = (n) => `<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${ICONS[n] || ''}</svg>`

const href = (lang, key) => prefix(lang) + SLUG[key]
const demoHref = base + 'demo/'
const abs = (p) => (config.url ? config.url + p : p)

function layout(lang, key, body) {
  const c = L[lang]
  const other = lang === 'fr' ? 'en' : 'fr'
  const m = c.meta[key]
  const title = sub(m.title)
  const nav = ['features', 'privacy', 'selfhost', 'offers']
  const here_ = href(lang, key)
  const alt = (l) => abs(prefix(l) + SLUG[key])
  const hasDemo = withDemo
  // Ressources en chemin relatif : le site s'affiche aussi ouvert depuis un dossier (file://). La 404 reste absolue (servie à n'importe quelle adresse).
  const rel = key === 'notfound' ? base : '../'.repeat((lang === 'en' ? 1 : 0) + (SLUG[key] ? 1 : 0)) || './'
  return `<!doctype html>
<html lang="${lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(title)}</title>
${m.description ? `<meta name="description" content="${t(m.description)}">` : ''}
${key === 'notfound' ? '<meta name="robots" content="noindex">' : `<link rel="canonical" href="${esc(abs(here_))}">
<link rel="alternate" hreflang="fr" href="${esc(alt('fr'))}">
<link rel="alternate" hreflang="en" href="${esc(alt('en'))}">
<link rel="alternate" hreflang="x-default" href="${esc(alt('fr'))}">`}
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${t(m.description || c.tagline)}">
<meta property="og:type" content="website">
<meta property="og:locale" content="${c.locale}">
<meta name="color-scheme" content="light dark">
<link rel="icon" href="${rel}assets/favicon.svg" type="image/svg+xml">
<link rel="preload" href="${rel}assets/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="${rel}assets/site.css">
</head>
<body>
<a class="skip" href="#main">${esc(c.nav.skip)}</a>
<header class="top">
  <div class="wrap bar">
    <a class="brand" href="${prefix(lang)}" ${key === 'home' ? 'aria-current="page"' : ''}><span class="mark" aria-hidden="true">${esc(config.name.charAt(0))}</span><span>${esc(config.name)}</span></a>
    <button class="menu" type="button" aria-expanded="false" aria-controls="nav">${esc(c.nav.menu)}</button>
    <nav id="nav" class="nav" aria-label="${esc(c.nav.menu)}">
      <ul>
        ${nav.map((k) => `<li><a href="${href(lang, k)}"${k === key ? ' aria-current="page"' : ''}>${esc(c.nav[k])}</a></li>`).join('\n        ')}
        <li><a class="lang" href="${href(other, key === 'notfound' ? 'home' : key)}" hreflang="${other}" lang="${other}">${esc(c.nav.language)}</a></li>
        ${hasDemo ? `<li><a class="btn small" href="${demoHref}">${esc(c.nav.demo)}</a></li>` : ''}
      </ul>
    </nav>
  </div>
</header>
<main id="main" tabindex="-1">
${body}
</main>
<footer class="foot">
  <div class="wrap">
    <p class="foot-brand"><strong>${esc(config.name)}</strong> · ${esc(c.tagline)}</p>
    <ul>
      <li><a href="${esc(config.repo)}">${esc(c.footer.source)}</a></li>
      <li><a href="${esc(config.repo)}/tree/main/docs">${esc(c.footer.docs)}</a></li>
      <li><a href="${esc(config.repo)}/blob/main/SECURITY.md">${esc(c.footer.security)}</a></li>
      <li><a href="${href(lang, 'contact')}">${esc(c.footer.contact)}</a></li>
      <li><a href="${href(lang, 'legal')}">${esc(c.footer.legal)}</a></li>
    </ul>
    <p class="muted">${esc(c.footer.copyright)}</p>
  </div>
</footer>
<script src="${rel}assets/site.js" defer></script>
</body>
</html>
`
}

const list = (items, cls = '') => `<ul class="${cls}">${items.map((i) => `<li>${t(i)}</li>`).join('')}</ul>`
const card = (ic, title, text) => `<article class="card">${icon(ic)}<h3>${t(title)}</h3><p>${t(text)}</p></article>`
const head = (c, k, extra = '') => `<section class="hero small"><div class="wrap"><h1>${t(c[k].h1)}</h1>${c[k].lead ? `<p class="lead">${t(c[k].lead)}</p>` : ''}${extra}</div></section>`

const pages = {
  home(lang) {
    const c = L[lang], h = c.home
    return `
<section class="hero"><div class="wrap">
  <p class="eyebrow">${t(h.eyebrow)}</p>
  <h1>${t(h.h1)}</h1>
  <p class="lead">${t(h.lead)}</p>
  <p class="cta">
    ${withDemo ? `<a class="btn" href="${demoHref}">${t(h.ctaDemo)}</a>` : ''}
    <a class="btn ghost" href="${href(lang, 'selfhost')}">${t(h.ctaInstall)}</a>
  </p>
  ${withDemo ? `<p class="muted">${t(h.note)}</p>` : ''}
</div></section>
<section class="band"><div class="wrap">
  <h2>${t(h.pillarsTitle)}</h2>
  <div class="grid3">${h.pillars.map((p) => card(p.icon, p.title, p.text)).join('')}</div>
</div></section>
${withDemo ? `<section class="wrap demo" id="demo">
  <h2>${t(h.demoTitle)}</h2><p>${t(h.demoText)}</p>
  <div class="frame" data-demo="${demoHref}" data-title="${esc(sub(h.demoTitle))}">
    <p><a class="btn" href="${demoHref}" data-launch>${t(h.demoLaunch)}</a> <a class="btn ghost" href="${demoHref}" target="_blank" rel="noopener">${t(h.demoOpen)}</a></p>
  </div>
</section>` : ''}
<section class="wrap">
  <h2>${t(h.stepsTitle)}</h2>
  <ol class="steps">${h.steps.map((s) => `<li><span class="n" aria-hidden="true">${esc(s.n)}</span><div><h3>${t(s.title)}</h3><p>${t(s.text)}</p></div></li>`).join('')}</ol>
</section>
<section class="band"><div class="wrap">
  <h2>${t(h.moreTitle)}</h2>
  <div class="grid2">${h.more.map((p) => card(p.icon, p.title, p.text)).join('')}</div>
</div></section>
<section class="wrap">
  <h2>${t(h.trustTitle)}</h2>
  <ul class="chips">${h.trust.map((x) => `<li>${icon('check')}${t(x)}</li>`).join('')}</ul>
</section>
<section class="final"><div class="wrap">
  <h2>${t(h.finalTitle)}</h2><p>${t(h.finalText)}</p>
  <p class="cta">${withDemo ? `<a class="btn" href="${demoHref}">${t(h.ctaDemo)}</a>` : ''}<a class="btn ghost" href="${href(lang, 'selfhost')}">${t(h.ctaInstall)}</a></p>
</div></section>`
  },
  features(lang) {
    const c = L[lang], f = c.features
    return head(c, 'features') + `<div class="wrap prose-grid">${f.groups.map((g) => `<section class="group"><h2>${icon(g.icon)}${t(g.title)}</h2>${g.text ? `<p>${t(g.text)}</p>` : ''}${list(g.items)}</section>`).join('')}
<section class="group notyet"><h2>${t(f.notYet)}</h2>${list(f.notYetItems)}</section></div>`
  },
  privacy(lang) {
    const c = L[lang], p = c.privacy
    return head(c, 'privacy') + `<div class="wrap prose-grid">${p.blocks.map((b) => `<section class="group"><h2>${t(b.title)}</h2>${list(b.items)}</section>`).join('')}
<section class="group notyet"><h2>${t(p.honestTitle)}</h2>${list(p.honest)}</section></div>
<div class="wrap"><p><a class="btn ghost" href="${esc(config.repo)}/blob/main/docs/SECURITE.md">${t(p.cta)}</a></p></div>`
  },
  selfhost(lang) {
    const c = L[lang], s = c.selfhost
    return head(c, 'selfhost') + `<div class="wrap narrow">
<h2>${t(s.needTitle)}</h2>${list(s.need)}
<h2>${t(s.stepsTitle)}</h2>
<ol class="steps">${s.steps.map((x, i) => `<li><span class="n" aria-hidden="true">${i + 1}</span><div><h3>${t(x.title)}</h3>${x.text ? `<p>${t(x.text)}</p>` : ''}${x.code ? `<pre><code>${t(x.code)}</code></pre>` : ''}</div></li>`).join('')}</ol>
<p class="note">${t(s.verified)}</p>
<p class="cta"><a class="btn" href="${esc(config.repo)}/blob/main/docs/INSTALL.md">${t(s.docs)}</a> <a class="btn ghost" href="${esc(config.repo)}/blob/main/docs/ADMIN.md">${t(s.admin)}</a> <a class="btn ghost" href="${esc(config.repo)}/blob/main/docs/UTILISATEUR.md">${t(s.user)}</a></p></div>`
  },
  offers(lang) {
    const c = L[lang], o = c.offers
    return head(c, 'offers') + `<div class="wrap"><div class="grid3 offers">${o.cards.map((x) => `<article class="card offer"><p class="tag">${t(x.tag)}</p><h2>${t(x.title)}</h2><p class="price">${t(x.price)}</p><p>${t(x.text)}</p>${list(x.points)}<p><a class="btn ghost" href="${href(lang, x.href)}">${t(x.cta)}</a></p></article>`).join('')}</div><p class="note">${t(o.note)}</p></div>`
  },
  contact(lang) {
    const c = L[lang], k = c.contact
    const mail = config.contactEmail
    return head(c, 'contact') + `<div class="wrap narrow">${mail ? `<p><a class="btn" href="mailto:${esc(mail)}">${t(k.mailLabel)} : ${esc(mail)}</a></p>` : `<p>${t(k.noMail)}</p>`}
<p><a class="btn ghost" href="${esc(config.repo)}/discussions">${t(k.repo)}</a></p><p class="muted">${t(k.security)}</p></div>`
  },
  legal(lang) {
    const c = L[lang], g = c.legal, l = config.legal
    const rows = [['publisher', l.publisher], ['form', l.form], ['address', l.address], ['director', l.director], ['host', l.host], ['registration', l.registration]].filter(([, v]) => v)
    return head(c, 'legal') + `<div class="wrap narrow"><dl class="defs">${rows.map(([k, v]) => `<dt>${t(g[k])}</dt><dd>${esc(v)}</dd>`).join('')}</dl>
<h2>${t(g.dataTitle)}</h2><p>${t(g.data)}</p><h2>${t(g.ipTitle)}</h2><p>${t(g.ip)}</p></div>`
  },
}

function write(rel, content) {
  const f = join(dist, rel)
  mkdirSync(dirname(f), { recursive: true })
  writeFileSync(f, content)
}

rmSync(dist, { recursive: true, force: true })
const urls = []
for (const lang of ['fr', 'en']) {
  for (const slug of PAGES) {
    const key = KEY[slug]
    const rel = (lang === 'fr' ? '' : 'en/') + slug + 'index.html'
    write(rel, layout(lang, key, pages[key](lang)))
    urls.push(prefix(lang) + slug)
  }
}
// 404 (en français, avec lien de retour)
{
  const c = fr.notfound
  write('404.html', layout('fr', 'notfound', `<section class="hero small"><div class="wrap"><h1>${t(c.h1)}</h1><p class="lead">${t(c.text)}</p><p><a class="btn" href="${base}">${t(c.home)}</a></p></div></section>`))
}
// Ressources
mkdirSync(join(dist, 'assets/fonts'), { recursive: true })
copyFileSync(join(here, 'assets/site.css'), join(dist, 'assets/site.css'))
copyFileSync(join(here, 'assets/site.js'), join(dist, 'assets/site.js'))
copyFileSync(join(here, 'assets/favicon.svg'), join(dist, 'assets/favicon.svg'))
const fonts = join(here, '../web/node_modules/@fontsource-variable')
for (const [src, dest] of [['inter/files/inter-latin-wght-normal.woff2', 'inter'], ['inter/files/inter-latin-ext-wght-normal.woff2', 'inter-ext'], ['space-grotesk/files/space-grotesk-latin-wght-normal.woff2', 'grotesk']]) {
  const p = join(fonts, src)
  if (!existsSync(p)) { console.error('✗ police introuvable : ' + p + ' (lance « npm ci » dans web/)'); process.exit(1) }
  copyFileSync(p, join(dist, `assets/fonts/${dest}.woff2`))
}
// Plan du site, robots
if (config.url) {
  write('sitemap.xml', `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.map((u) => `  <url><loc>${esc(config.url + u)}</loc></url>`).join('\n')}\n</urlset>\n`)
  write('robots.txt', `User-agent: *\nAllow: /\nSitemap: ${config.url}${base}sitemap.xml\n`)
} else {
  write('robots.txt', 'User-agent: *\nAllow: /\n')
}

// Démo : l'application elle-même, en données fictives, sous /demo/
if (withDemo) {
  const r = spawnSync('npm', ['run', 'build', '--', '--outDir', join(dist, 'demo'), '--emptyOutDir'], {
    cwd: join(here, '../web'), stdio: 'inherit',
    env: { ...process.env, VITE_BASE: base + 'demo/', VITE_USE_MOCK: 'true', VITE_DEMO: 'true', VITE_DEMO_LINK: base },
  })
  if (r.status !== 0) { console.error('✗ la démo ne se construit pas'); process.exit(1) }
  // Hébergement statique : toute adresse de la démo retombe sur son index (routage côté client)
  copyFileSync(join(dist, 'demo/index.html'), join(dist, 'demo/404.html'))
}
console.log(`✓ site construit dans ${dist} (${urls.length} pages${withDemo ? ' + démo' : ''})`)
