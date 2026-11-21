<?php

namespace App\Mail;

/** Déplace un message d'un dossier IMAP à un autre (archiver, corbeille, remettre en boîte de réception). Jamais de suppression définitive. */
interface MailboxMover
{
    /** @return ?int nouvel UID dans le dossier d'arrivée, null si le serveur ne permet pas de le retrouver */
    public function move(string $folder, int $uid, string $messageId, string $target): ?int;
}
