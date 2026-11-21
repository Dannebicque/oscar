<?php

namespace App\Mail;

/**
 * Dossiers et sous-dossiers de la messagerie (IMAP). Les chemins sont en UTF-8, avec le séparateur du serveur.
 * Jamais de suppression de message : un dossier ne se supprime que vide.
 */
interface MailboxFolders
{
    /** @return list<array{path: string, name: string, delimiter: string}> */
    public function folders(): array;

    public function createFolder(string $path): void;

    public function renameFolder(string $from, string $to): void;

    public function deleteFolder(string $path): void;

    /** Nombre de messages que le serveur garde dans ce dossier. */
    public function countMessages(string $path): int;

    /** Les derniers messages d'un dossier (première visite d'un dossier existant : pas tout l'historique). @return list<MailHeader> */
    public function fetchRecent(string $folder, int $limit): array;
}
