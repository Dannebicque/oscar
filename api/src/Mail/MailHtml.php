<?php

namespace App\Mail;

/**
 * HTML d'un mail reçu (F-16, §3) : un mail est une donnée non fiable. Rien n'est repris tel quel ; le message est RECONSTRUIT à partir
 * d'une liste blanche de balises, d'attributs et de propriétés de style. Ni script, ni formulaire, ni cadre, ni feuille de style,
 * ni lien javascript. Les images distantes (dont les pixels espions) ne sont chargées que si l'utilisateur le demande.
 * Le front ajoute une seconde barrière : un cadre sans script, avec sa propre politique de sécurité du contenu.
 */
final class MailHtml
{
    /** Au-delà, on s'en tient au texte : un message démesuré ne doit pas figer le lecteur. */
    public const MAX = 600_000;
    private const MAX_NODES = 15_000;
    private const MAX_DEPTH = 60;
    private const MAX_INLINE = 2_000_000;

    private const DROP = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'noscript', 'title', 'head', 'template', 'audio', 'video', 'canvas', 'map', 'area'];
    private const VOID = ['br', 'hr', 'img', 'col'];
    private const ALLOWED = [
        'a', 'abbr', 'address', 'article', 'b', 'bdi', 'bdo', 'big', 'blockquote', 'br', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'dfn', 'div', 'dl', 'dt',
        'em', 'figcaption', 'figure', 'font', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'i', 'img', 'ins', 'kbd', 'li', 'main', 'mark', 'ol', 'p', 'pre', 'q',
        's', 'samp', 'section', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'tt', 'u', 'ul',
    ];
    private const ANY_ATTRS = ['title', 'dir', 'lang', 'align'];
    private const ATTRS = [
        'a' => ['href'], 'img' => ['alt', 'width', 'height'], 'font' => ['color', 'face', 'size'],
        'table' => ['width', 'height', 'border', 'cellpadding', 'cellspacing', 'bgcolor', 'role'],
        'tr' => ['valign', 'bgcolor'], 'tbody' => ['valign'], 'thead' => ['valign'],
        'td' => ['colspan', 'rowspan', 'valign', 'width', 'height', 'bgcolor', 'nowrap'], 'th' => ['colspan', 'rowspan', 'valign', 'width', 'height', 'bgcolor', 'nowrap'],
        'col' => ['span', 'width'], 'colgroup' => ['span', 'width'], 'ol' => ['start', 'type'],
    ];
    private const NUMERIC = ['width', 'height', 'border', 'cellpadding', 'cellspacing', 'colspan', 'rowspan', 'span', 'start', 'size'];
    private const STYLE_PROPS = [
        'color', 'background', 'background-color', 'font', 'font-family', 'font-size', 'font-weight', 'font-style', 'font-variant', 'text-decoration', 'text-align', 'text-transform', 'text-indent',
        'line-height', 'letter-spacing', 'word-spacing', 'white-space', 'word-break', 'overflow-wrap', 'vertical-align', 'direction', 'list-style', 'list-style-type',
        'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right', 'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right',
        'border', 'border-top', 'border-bottom', 'border-left', 'border-right', 'border-color', 'border-width', 'border-style', 'border-radius', 'border-collapse', 'border-spacing',
        'width', 'height', 'min-width', 'max-width', 'min-height', 'max-height', 'display', 'float', 'clear', 'table-layout',
    ];

    private int $nodes = 0;
    private int $blocked = 0;
    private int $inlineBytes = 0;
    private bool $remote = false;
    /** @var array<string, array{type: string, content: string}> */
    private array $inline = [];

    /**
     * @param array<string, array{type: string, content: string}> $inline images jointes au corps (« cid: »)
     * @return ?array{html: string, blockedImages: int} null : rien d'affichable (trop gros, vide, illisible), on garde le texte
     */
    public function render(string $html, array $inline = [], bool $remoteImages = false): ?array
    {
        if (\strlen($html) > self::MAX || trim($html) === '') {
            return null;
        }
        [$this->nodes, $this->blocked, $this->inlineBytes, $this->remote, $this->inline] = [0, 0, 0, $remoteImages, $inline];
        try {
            $body = \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR, 'UTF-8')->body;
            if ($body === null) {
                return null;
            }
            // Le fond et la couleur du message sont souvent posés sur <body> : on les reporte sur un bloc englobant.
            $out = $this->children($body, 0);
            $wrap = $this->style($body->getAttribute('style') ?? '');
            if (($bg = $this->color($body->getAttribute('bgcolor') ?? '')) !== null) {
                $wrap = 'background-color: '.$bg.($wrap === '' ? '' : '; '.$wrap);
            }
        } catch (\Throwable) {
            return null;
        }
        if ($this->nodes > self::MAX_NODES || trim($out) === '') {
            return null;
        }
        if ($wrap !== '') {
            $out = '<div style="'.$this->esc($wrap).'">'.$out.'</div>';
        }

        return ['html' => $out, 'blockedImages' => $this->blocked];
    }

    private function children(\Dom\Node $node, int $depth): string
    {
        $out = '';
        foreach ($node->childNodes as $c) {
            if (++$this->nodes > self::MAX_NODES) {
                break;
            }
            if ($c instanceof \Dom\Text) {
                $out .= $this->esc($c->data);
            } elseif ($c instanceof \Dom\Element) {
                $out .= $this->element($c, $depth + 1);
            }
        }

        return $out;
    }

    private function element(\Dom\Element $e, int $depth): string
    {
        $tag = strtolower($e->localName);
        if (\in_array($tag, self::DROP, true)) {
            return '';
        }
        if ($depth > self::MAX_DEPTH) {
            return $this->esc($e->textContent ?? ''); // trop imbriqué : le texte reste lisible
        }
        if (!\in_array($tag, self::ALLOWED, true)) {
            return $this->children($e, $depth); // balise inconnue : on garde le contenu, pas la balise
        }
        $attrs = '';
        foreach ([...self::ANY_ATTRS, ...(self::ATTRS[$tag] ?? [])] as $name) {
            if ($e->hasAttribute($name) && ($v = $this->attribute($name, trim($e->getAttribute($name)))) !== null) {
                $attrs .= ' '.$name.'="'.$this->esc($v).'"';
            }
        }
        $style = $this->style($e->getAttribute('style') ?? '');
        if ($style !== '') {
            $attrs .= ' style="'.$this->esc($style).'"';
        }
        if ($tag === 'img') {
            return $this->image(trim($e->getAttribute('src') ?? ''), $attrs);
        }
        if ($tag === 'a' && str_contains($attrs, ' href="')) {
            // Nouvelle fenêtre, sans lien retour ; l'adresse réelle se lit au survol (un libellé peut mentir).
            // Le titre de l'expéditeur est remplacé : il pourrait annoncer une autre adresse que la vraie.
            $attrs = (string) preg_replace('/ title="[^"]*"/', '', $attrs);
            $attrs .= ' target="_blank" rel="noopener noreferrer nofollow" title="'.$this->esc(trim($e->getAttribute('href'))).'"';
        }
        if (\in_array($tag, self::VOID, true)) {
            return '<'.$tag.$attrs.'>';
        }

        return '<'.$tag.$attrs.'>'.$this->children($e, $depth).'</'.$tag.'>';
    }

    private function attribute(string $name, string $v): ?string
    {
        if ($name === 'href') {
            return preg_match('~^(https?://|mailto:|tel:)[^\s<>"\x00-\x1f]*$~i', $v) ? $v : null;
        }
        if ($name === 'bgcolor' || $name === 'color') {
            return $this->color($v);
        }
        if (\in_array($name, self::NUMERIC, true)) {
            return preg_match('/^[+-]?\d{1,4}%?$/', $v) ? $v : null;
        }
        if ($name === 'align' || $name === 'valign') {
            return \in_array(strtolower($v), ['left', 'right', 'center', 'justify', 'top', 'middle', 'bottom', 'baseline'], true) ? $v : null;
        }
        if ($name === 'dir') {
            return \in_array(strtolower($v), ['ltr', 'rtl', 'auto'], true) ? $v : null;
        }
        if ($name === 'role') {
            return $v === 'presentation' ? $v : null;
        }

        return mb_substr($v, 0, 300);
    }

    private function image(string $src, string $attrs): string
    {
        if (preg_match('~^cid:(.+)$~i', $src, $m)) {
            $part = $this->inline[rawurldecode($m[1])] ?? null;
            $type = strtolower($part['type'] ?? '');
            if ($part === null || !\in_array($type, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
                || $this->inlineBytes + \strlen($part['content']) > self::MAX_INLINE) {
                return $this->missing($attrs);
            }
            $this->inlineBytes += \strlen($part['content']);

            return '<img src="data:'.$type.';base64,'.base64_encode($part['content']).'"'.$attrs.'>';
        }
        if (preg_match('~^data:image/(png|jpeg|gif|webp);base64,[a-z0-9+/=\s]+$~i', $src) && \strlen($src) <= self::MAX) {
            return '<img src="'.$this->esc($src).'"'.$attrs.'>';
        }
        if (preg_match('~^(https?:)?//[^\s<>"\x00-\x1f]+$~i', $src)) {
            // Un pixel espion (1×1 ou 0) ne se charge jamais, même sur demande : il n'apporte rien à la personne qui lit.
            if (preg_match('/ (width|height)="[01]"/', $attrs)) {
                return '';
            }
            if ($this->remote) {
                // Toujours en https : jamais de chargement en clair.
                $https = preg_replace('~^(https?:)?//~i', 'https://', $src);

                return '<img src="'.$this->esc((string) $https).'" referrerpolicy="no-referrer"'.$attrs.'>';
            }
            ++$this->blocked;

            return $this->missing($attrs);
        }

        return $this->missing($attrs);
    }

    /** Image non chargée : son texte de remplacement, pour que le message reste compréhensible. */
    private function missing(string $attrs): string
    {
        return preg_match('/ alt="([^"]+)"/', $attrs, $m) ? '<span>['.$m[1].']</span>' : '';
    }

    private function color(string $v): ?string
    {
        $v = trim($v);

        return preg_match('/^(#?[0-9a-f]{3,8}|[a-z]{3,20})$/i', $v) ? $v : null;
    }

    private function style(string $css): string
    {
        $out = [];
        foreach (explode(';', mb_substr($css, 0, 2000)) as $decl) {
            if (!str_contains($decl, ':')) {
                continue;
            }
            [$prop, $value] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            $value = trim((string) preg_replace('/\s*!\s*important$/i', '', $value));
            if (!\in_array($prop, self::STYLE_PROPS, true) || $value === '' || mb_strlen($value) > 200) {
                continue;
            }
            // Pas d'échappement CSS (« \ »), pas de fonction qui charge ou calcule autre chose qu'une couleur.
            if (!preg_match('/^[a-z0-9#%\s.,()\/\-"\']+$/i', $value) || str_contains($value, '/*') || preg_match('/(?!rgba?\()(?!hsla?\()\b[a-z-]+\(/i', $value)) {
                continue;
            }
            $out[] = $prop.': '.$value;
        }

        return implode('; ', $out);
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
