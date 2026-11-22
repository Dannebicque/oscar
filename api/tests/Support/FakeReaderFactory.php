<?php

namespace App\Tests\Support;

use App\Entity\MailAccount;
use App\Mail\MailboxAppender;
use App\Mail\MailboxDraftReader;
use App\Mail\MailboxFolders;
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
    /** @var array{cc: list<string>, bcc: list<string>} servis par draftRecipients */
    public static array $draftRecipients = ['cc' => [], 'bcc' => []];
    /** @var list<string> dossiers personnels simulés (chemins avec « / ») */
    public static array $folders = [];
    /** @var array<string, int> messages que le serveur garde par dossier */
    public static array $serverCount = [];
    /** @var list<array{string, string}> renommages demandés */
    public static array $renamed = [];
    /** @var list<\App\Mail\MailHeader> en-têtes servis par fetchRecent */
    public static array $recent = [];

    public function __construct(CredentialVault $vault)
    {
        parent::__construct($vault);
    }

    public function create(MailAccount $a): MailboxReader&MailboxAppender&MailboxMover&MailboxFolders&MailboxDraftReader
    {
        if ($a->getImapPort() === 1) {
            throw new \RuntimeException('connexion refusée (port 1, simulé)');
        }

        return new class implements MailboxReader, MailboxAppender, MailboxMover, MailboxFolders, MailboxDraftReader {
            public function move(string $folder, int $uid, string $messageId, string $target): ?int
            {
                if (FakeReaderFactory::$moveFails) {
                    throw new \RuntimeException('imap down');
                }
                FakeReaderFactory::$moved[] = [$folder, $uid, $target];

                return 500 + $uid;
            }
            public function draftRecipients(string $folder, int $uid): array { return FakeReaderFactory::$draftRecipients; }
            public function folders(): array
            {
                $base = array_map(static fn (string $p) => ['path' => $p, 'name' => $p, 'delimiter' => '/'], ['INBOX', 'Sent', 'Drafts', 'Trash', 'Archive']);

                return [...$base, ...array_map(static fn (string $p) => ['path' => $p, 'name' => basename($p), 'delimiter' => '/'], FakeReaderFactory::$folders)];
            }
            public function createFolder(string $path): void { FakeReaderFactory::$folders[] = $path; }
            public function renameFolder(string $from, string $to): void
            {
                FakeReaderFactory::$renamed[] = [$from, $to];
                FakeReaderFactory::$folders = array_map(static fn (string $p) => $p === $from ? $to : (str_starts_with($p, $from.'/') ? $to.substr($p, \strlen($from)) : $p), FakeReaderFactory::$folders);
            }
            public function deleteFolder(string $path): void { FakeReaderFactory::$folders = array_values(array_filter(FakeReaderFactory::$folders, static fn (string $p) => $p !== $path)); }
            public function countMessages(string $path): int { return FakeReaderFactory::$serverCount[$path] ?? 0; }
            public function fetchRecent(string $folder, int $limit): array { return array_slice(FakeReaderFactory::$recent, -$limit); }
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
