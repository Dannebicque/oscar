<?php

namespace App\Mail;

/** Dépose une copie d'un message envoyé dans un dossier IMAP (Envoyés). */
interface MailboxAppender
{
    public function append(string $folder, string $rawMessage): void;
}
