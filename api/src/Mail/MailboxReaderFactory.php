<?php

namespace App\Mail;

use App\Entity\MailAccount;
use App\Security\CredentialVault;

/** Construit le lecteur IMAP d'un compte : le mot de passe n'est déchiffré qu'ici, au moment de se connecter. */
class MailboxReaderFactory
{
    public function __construct(private readonly CredentialVault $vault)
    {
    }

    public function create(MailAccount $a): MailboxReader&MailboxAppender&MailboxMover&MailboxFolders&MailboxDraftReader
    {
        return new WebklexMailboxReader($a->getImapHost(), $a->getImapPort(), $a->getUsername(), $this->vault->decrypt($a->getPasswordEncrypted()), $a->getImapEncryption(), $a->getImapEncryption() !== 'none');
    }
}
