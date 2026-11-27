<?php

namespace App\Mail;

/** Lecture d'une boîte. Synchronisation par UID, de façon incrémentale (§3 du cahier des charges). */
interface MailboxReader
{
    /**
     * @return list<MailHeader> messages dont l'UID est strictement supérieur à $afterUid, par UID croissant
     */
    public function fetchAfter(string $folder, int $afterUid): array;

    /** Corps en TEXTE BRUT d'un message, lu à la demande (rédaction, IA). */
    public function fetchBody(string $folder, int $uid): ?string;

    /** Texte, HTML brut et images du corps, en une seule lecture. Le HTML ne s'affiche qu'après MailHtml (F-16). null : rien à montrer. */
    public function fetchContent(string $folder, int $uid): ?MailContent;

    /** @return list<array{name: string, size: int, type: string}> pièces jointes (métadonnées seulement) */
    public function attachments(string $folder, int $uid): array;

    /** @return ?array{name: string, type: string, content: string} contenu brut d'une pièce jointe, servi en téléchargement uniquement */
    public function attachment(string $folder, int $uid, int $index): ?array;

    /** Texte iCalendar (text/calendar) d'une invitation jointe au message, s'il y en a une. Lu à la demande, jamais exécuté. */
    public function invitation(string $folder, int $uid): ?string;
}
