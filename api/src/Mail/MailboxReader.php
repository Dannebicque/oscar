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

    /** @return list<array{name: string, size: int, type: string}> pièces jointes (métadonnées seulement) */
    public function attachments(string $folder, int $uid): array;

    /** @return ?array{name: string, type: string, content: string} contenu brut d'une pièce jointe, servi en téléchargement uniquement */
    public function attachment(string $folder, int $uid, int $index): ?array;

    /** Texte iCalendar (text/calendar) d'une invitation jointe au message, s'il y en a une. Lu à la demande, jamais exécuté. */
    public function invitation(string $folder, int $uid): ?string;
}
