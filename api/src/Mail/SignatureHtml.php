<?php

namespace App\Mail;

/**
 * Signature HTML (F-19) : on n'envoie jamais le HTML de l'utilisateur tel quel. Il est reconstruit à partir d'une liste blanche
 * de balises, d'attributs et de propriétés de style : ni script, ni formulaire, ni cadre, ni lien javascript, ni image ailleurs qu'en https.
 * Le texte brut équivalent est dérivé du HTML assaini : c'est lui qui s'insère dans le champ de rédaction.
 */
final class SignatureHtml
{
    public const MAX = 10_000;

    private const DROP = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'noscript', 'title', 'head', 'template', 'audio', 'video', 'canvas'];
    private const VOID = ['br', 'hr', 'img'];
    private const ALLOWED = ['a', 'b', 'strong', 'i', 'em', 'u', 's', 'small', 'sub', 'sup', 'br', 'hr', 'p', 'div', 'span', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'img', 'h1', 'h2', 'h3', 'h4', 'blockquote'];
    private const ATTRS = [
        'a' => ['href', 'title'], 'img' => ['src', 'alt', 'width', 'height'], 'table' => ['width', 'border', 'cellpadding', 'cellspacing', 'align'],
        'td' => ['colspan', 'rowspan', 'align', 'valign', 'width', 'height'], 'th' => ['colspan', 'rowspan', 'align', 'valign', 'width', 'height'],
        'p' => ['align'], 'div' => ['align'], 'span' => [],
    ];
    private const STYLE_PROPS = [
        'color', 'background-color', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'text-align', 'line-height', 'letter-spacing',
        'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right', 'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right',
        'border', 'border-top', 'border-bottom', 'border-left', 'border-right', 'width', 'height', 'max-width', 'vertical-align', 'white-space',
    ];
    private const BLOCK = ['p', 'div', 'tr', 'li', 'ul', 'ol', 'table', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'hr'];

    /** @throws \InvalidArgumentException si le HTML est trop long */
    public function sanitize(string $html): string
    {
        if (mb_strlen($html) > self::MAX) {
            throw new \InvalidArgumentException('Signature HTML trop longue.');
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="oscar-root">'.$html.'</div>', \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $dom->getElementById('oscar-root');
        $out = $root === null ? '' : $this->children($root, 0);
        $out = trim($out);
        if (mb_strlen($out) > self::MAX) {
            throw new \InvalidArgumentException('Signature HTML trop longue.');
        }

        return $out;
    }

    /** Texte brut d'un HTML déjà assaini, avec le séparateur « -- » habituel en tête. */
    public function toText(string $safeHtml): string
    {
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="oscar-root">'.$safeHtml.'</div>', \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $dom->getElementById('oscar-root');
        $text = $root === null ? '' : $this->text($root);
        $lines = array_map(static fn (string $l) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $l) ?? $l), explode("\n", $text));
        $joined = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '');

        return $joined === '' ? '' : "-- \n".$joined;
    }

    private function children(\DOMNode $node, int $depth): string
    {
        if ($depth > 30) {
            return '';
        }
        $out = '';
        foreach ($node->childNodes as $c) {
            if ($c instanceof \DOMText) {
                $out .= htmlspecialchars($c->nodeValue ?? '', \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($c instanceof \DOMElement) {
                $out .= $this->element($c, $depth + 1);
            }
        }

        return $out;
    }

    private function element(\DOMElement $e, int $depth): string
    {
        $tag = strtolower($e->tagName);
        if (\in_array($tag, self::DROP, true)) {
            return '';
        }
        if (!\in_array($tag, self::ALLOWED, true)) {
            return $this->children($e, $depth); // balise inconnue : on garde le texte, pas la balise
        }
        $attrs = '';
        foreach (self::ATTRS[$tag] ?? [] as $name) {
            if (!$e->hasAttribute($name)) {
                continue;
            }
            $v = trim($e->getAttribute($name));
            if ($name === 'href') {
                if (!preg_match('~^(https?://|mailto:|tel:)[^\s<>"\x00-\x1f]*$~i', $v)) {
                    continue;
                }
            } elseif ($name === 'src') {
                if (!preg_match('~^https://[^\s<>"\x00-\x1f]*$~i', $v)) {
                    continue;
                }
            } elseif (\in_array($name, ['width', 'height', 'border', 'cellpadding', 'cellspacing', 'colspan', 'rowspan'], true)) {
                if (!preg_match('/^\d{1,4}%?$/', $v)) {
                    continue;
                }
            } elseif (\in_array($name, ['align', 'valign'], true)) {
                if (!\in_array(strtolower($v), ['left', 'right', 'center', 'top', 'middle', 'bottom'], true)) {
                    continue;
                }
            } else {
                $v = mb_substr($v, 0, 200);
            }
            $attrs .= ' '.$name.'="'.htmlspecialchars($v, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"';
        }
        if ($tag === 'a' && str_contains($attrs, 'href=')) {
            $attrs .= ' rel="noopener noreferrer"';
        }
        if ($e->hasAttribute('style') && ($style = $this->style($e->getAttribute('style'))) !== '') {
            $attrs .= ' style="'.htmlspecialchars($style, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"';
        }
        if (\in_array($tag, self::VOID, true)) {
            return $tag === 'img' && !str_contains($attrs, 'src=') ? '' : '<'.$tag.$attrs.'>';
        }

        return '<'.$tag.$attrs.'>'.$this->children($e, $depth).'</'.$tag.'>';
    }

    private function style(string $css): string
    {
        $out = [];
        foreach (explode(';', $css) as $decl) {
            if (!str_contains($decl, ':')) {
                continue;
            }
            [$prop, $value] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (!\in_array($prop, self::STYLE_PROPS, true) || $value === '' || mb_strlen($value) > 120) {
                continue;
            }
            if (!preg_match('/^[a-z0-9#%\s.,()\-"\']+$/i', $value) || preg_match('/url\s*\(|expression|javascript|behavior|import/i', $value)) {
                continue;
            }
            $out[] = $prop.': '.$value;
        }

        return implode('; ', $out);
    }

    private function text(\DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $c) {
            if ($c instanceof \DOMText) {
                $out .= str_replace(["\r", "\n"], ' ', $c->nodeValue ?? '');
            } elseif ($c instanceof \DOMElement) {
                $tag = strtolower($c->tagName);
                if (\in_array($tag, self::DROP, true)) {
                    continue;
                }
                if ($tag === 'br') {
                    $out .= "\n";
                    continue;
                }
                $inner = $this->text($c);
                if ($tag === 'a' && $c->hasAttribute('href')) {
                    $href = trim($c->getAttribute('href'));
                    $plain = preg_replace('~^mailto:|^tel:~i', '', $href) ?? $href;
                    $inner = trim($inner);
                    $inner = ($inner === '' || $inner === $plain || $inner === $href) ? $plain : $inner.' ('.$plain.')';
                } elseif ($tag === 'img') {
                    $inner = trim($c->getAttribute('alt'));
                } elseif ($tag === 'li') {
                    $inner = '- '.trim($inner);
                } elseif (\in_array($tag, ['td', 'th'], true)) {
                    $inner = trim($inner).'  ';
                }
                $out .= \in_array($tag, self::BLOCK, true) ? "\n".$inner."\n" : $inner;
            }
        }

        return $out;
    }
}
