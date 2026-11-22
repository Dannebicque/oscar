<?php

namespace App\Mail;

/** Qui écrit : l'adresse « De », son nom, l'éventuelle adresse « Répondre à » et la signature HTML à ajouter (identité personnelle ou boîte partagée). */
final readonly class SenderIdentity
{
    public function __construct(
        public string $address,
        public string $name,
        public ?string $replyTo = null,
        public ?string $signature = null,
        public ?string $signatureHtml = null,
    ) {
    }
}
