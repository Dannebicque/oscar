<?php

namespace App\Mail;

/**
 * État des messages sur le serveur de messagerie, qui fait foi : un mail lu, supprimé ou déplacé depuis un autre logiciel
 * l'est aussi dans Oscar.
 */
interface MailboxFlags
{
    /** @return list<int> UID de tous les messages du dossier. Lève une exception si le dossier est injoignable : jamais une liste vide par défaut. */
    public function uids(string $folder): array;

    /** @return list<int> UID des messages non lus du dossier */
    public function unseenUids(string $folder): array;

    public function setSeen(string $folder, int $uid, bool $seen): void;
}
