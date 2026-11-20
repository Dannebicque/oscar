<?php

namespace App\Mail;

/** Lecture d'une boîte. Synchronisation par UID, de façon incrémentale (§3 du cahier des charges). */
interface MailboxReader
{
    /**
     * @return list<MailHeader> messages dont l'UID est strictement supérieur à $afterUid, par UID croissant
     */
    public function fetchAfter(string $folder, int $afterUid): array;
}
