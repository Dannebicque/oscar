<?php

namespace App\Tests\Support;

use App\Entity\MailAccount;
use App\Mail\MailboxAppender;
use App\Mail\MailboxReader;
use App\Mail\MailboxReaderFactory;
use App\Security\CredentialVault;

/** Remplace IMAP dans les tests d'API : enregistre les copies déposées dans Envoyés. */
final class FakeReaderFactory extends MailboxReaderFactory
{
    public static bool $appendFails = false;
    /** @var list<array{string, string}> */
    public static array $appended = [];

    public function __construct(CredentialVault $vault)
    {
        parent::__construct($vault);
    }

    public function create(MailAccount $a): MailboxReader&MailboxAppender
    {
        if ($a->getImapPort() === 1) {
            throw new \RuntimeException('connexion refusée (port 1, simulé)');
        }

        return new class implements MailboxReader, MailboxAppender {
            public function fetchAfter(string $folder, int $afterUid): array { return []; }
            public function fetchBody(string $folder, int $uid): ?string { return 'Corps simulé'; }
            public function append(string $folder, string $rawMessage): void
            {
                if (FakeReaderFactory::$appendFails) {
                    throw new \RuntimeException('imap down');
                }
                FakeReaderFactory::$appended[] = [$folder, $rawMessage];
            }
        };
    }
}
