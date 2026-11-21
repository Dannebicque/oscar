<?php

namespace App\Tests\Support;

use App\Entity\MailAccount;
use App\Mail\MailboxAppender;
use App\Mail\MailboxMover;
use App\Mail\MailboxReader;
use App\Mail\MailboxReaderFactory;
use App\Security\CredentialVault;

/** Remplace IMAP dans les tests d'API : enregistre les copies déposées dans Envoyés. */
final class FakeReaderFactory extends MailboxReaderFactory
{
    public static bool $appendFails = false;
    /** @var list<array{string, string}> */
    public static array $appended = [];
    /** @var list<array{string, int, string}> */
    public static array $moved = [];
    public static bool $moveFails = false;
    public static ?string $ics = null;

    public function __construct(CredentialVault $vault)
    {
        parent::__construct($vault);
    }

    public function create(MailAccount $a): MailboxReader&MailboxAppender&MailboxMover
    {
        if ($a->getImapPort() === 1) {
            throw new \RuntimeException('connexion refusée (port 1, simulé)');
        }

        return new class implements MailboxReader, MailboxAppender, MailboxMover {
            public function move(string $folder, int $uid, string $messageId, string $target): ?int
            {
                if (FakeReaderFactory::$moveFails) {
                    throw new \RuntimeException('imap down');
                }
                FakeReaderFactory::$moved[] = [$folder, $uid, $target];

                return 500 + $uid;
            }
            public function invitation(string $folder, int $uid): ?string { return FakeReaderFactory::$ics; }
            public function attachments(string $folder, int $uid): array { return [['name' => 'convention.pdf', 'size' => 2048, 'type' => 'application/pdf']]; }
            public function attachment(string $folder, int $uid, int $index): ?array { return $index === 0 ? ['name' => 'convention.pdf', 'type' => 'application/pdf', 'content' => '%PDF-simulé'] : null; }
            public function fetchAfter(string $folder, int $afterUid): array { return []; }
            public function fetchBody(string $folder, int $uid): ?string { return 'Corps simulé'; }
            public function append(string $folder, string $rawMessage, array $flags = ['\\Seen']): void
            {
                if (FakeReaderFactory::$appendFails) {
                    throw new \RuntimeException('imap down');
                }
                FakeReaderFactory::$appended[] = [$folder, $rawMessage];
            }
        };
    }
}
