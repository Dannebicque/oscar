<?php

namespace App\Mail;

/** Champs d'un brouillon que la liste des mails ne garde pas : les destinataires en copie (Cc) et en copie cachée (Cci). */
interface MailboxDraftReader
{
    /** @return array{cc: list<string>, bcc: list<string>} */
    public function draftRecipients(string $folder, int $uid): array;
}
