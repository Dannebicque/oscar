<?php

namespace App\Mail;

/** État « lu » (drapeau IMAP \Seen). Le serveur de messagerie fait foi : un mail lu ailleurs est lu dans Oscar, et inversement. */
interface MailboxFlags
{
    /** @return list<int> UID des messages non lus du dossier */
    public function unseenUids(string $folder): array;

    public function setSeen(string $folder, int $uid, bool $seen): void;
}
