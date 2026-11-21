<?php

namespace App\Mail;

/** Dépose une copie d'un message envoyé dans un dossier IMAP (Envoyés). */
interface MailboxAppender
{
    /** @param list<string> $flags drapeaux IMAP (\\Seen pour une copie envoyée, \\Draft pour un brouillon) */
    public function append(string $folder, string $rawMessage, array $flags = ['\\Seen']): void;
}
