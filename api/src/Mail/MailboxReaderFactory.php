<?php

namespace App\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Construit le lecteur IMAP du compte configuré (un seul compte pour l'instant). */
final class MailboxReaderFactory
{
    public function __construct(
        #[Autowire('%env(IMAP_HOST)%')] private readonly string $host,
        #[Autowire('%env(int:IMAP_PORT)%')] private readonly int $port,
        #[Autowire('%env(IMAP_USER)%')] private readonly string $user,
        #[Autowire('%env(IMAP_PASSWORD)%')] private readonly string $password,
        #[Autowire('%env(default::IMAP_ENCRYPTION)%')] private readonly ?string $encryption = null,
    ) {
    }

    public function account(): string
    {
        return $this->user;
    }

    public function create(): MailboxReader
    {
        $enc = $this->encryption ?: 'none';

        return new WebklexMailboxReader($this->host, $this->port, $this->user, $this->password, $enc, $enc !== 'none');
    }
}
