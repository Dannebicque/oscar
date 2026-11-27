<?php

namespace App\Mail;

/** Contenu d'un message tel que lu sur le serveur : texte, HTML BRUT (jamais montré sans passer par MailHtml) et images jointes au corps. */
final class MailContent
{
    /** @param array<string, array{type: string, content: string}> $inline images « cid: » par identifiant */
    public function __construct(
        public readonly string $text,
        public readonly ?string $html = null,
        public readonly array $inline = [],
    ) {
    }
}
