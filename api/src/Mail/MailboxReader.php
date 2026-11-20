<?php

namespace App\Mail;

/** Lecture d'une boîte. Synchronisation par UID, de façon incrémentale (§3 du cahier des charges). */
interface MailboxReader
{
    /**
     * @return list<MailHeader> messages dont l'UID est strictement supérieur à $afterUid, par UID croissant
     */
    public function fetchAfter(string $folder, int $afterUid): array;

    /**
     * Corps en TEXTE BRUT d'un message, lu à la demande. Jamais de HTML : un mail est une donnée non fiable
     * (contenus actifs, pixels espions) ; l'affichage HTML isolé viendra plus tard (§3).
     */
    public function fetchBody(string $folder, int $uid): ?string;
}
