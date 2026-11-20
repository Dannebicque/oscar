<?php

namespace App\Tests\Mail;

use App\Mail\MailHeader;
use App\Mail\ReplyDetector;
use App\Mail\WebklexMailboxReader;
use PHPUnit\Framework\TestCase;

/**
 * Contre le Dovecot de test (docker/dovecot). Ignoré si le serveur n'est pas lancé :
 *   docker compose -f docker/dovecot/compose.yaml up -d && python3 docker/dovecot/seed.py
 */
final class DovecotIntegrationTest extends TestCase
{
    private WebklexMailboxReader $reader;

    protected function setUp(): void
    {
        $host = $_SERVER['IMAP_HOST'] ?? '127.0.0.1';
        $port = (int) ($_SERVER['IMAP_PORT'] ?? 1143);
        $sock = @fsockopen($host, $port, $errno, $errstr, 0.5);
        if ($sock === false) {
            self::markTestSkipped("Dovecot de test indisponible sur $host:$port");
        }
        fclose($sock);
        $this->reader = new WebklexMailboxReader($host, $port, $_SERVER['IMAP_USER'] ?? 'david', $_SERVER['IMAP_PASSWORD'] ?? 'oscar-test', 'none', false);
    }

    public function testReadsInboxAndSentIncrementally(): void
    {
        $inbox = $this->reader->fetchAfter('INBOX', 0);
        $sent = $this->reader->fetchAfter('Sent', 0);
        self::assertGreaterThanOrEqual(4, \count($inbox));
        self::assertGreaterThanOrEqual(2, \count($sent));
        $lastUid = max(array_map(static fn (MailHeader $h) => $h->uid, $inbox));
        self::assertSame([], $this->reader->fetchAfter('INBOX', $lastUid), 'rien après le dernier UID');
        self::assertCount(1, $this->reader->fetchAfter('INBOX', $lastUid - 1));
    }

    public function testFixtureScenarios(): void
    {
        $inbox = $this->reader->fetchAfter('INBOX', 0);
        $sent = $this->reader->fetchAfter('Sent', 0);
        $detector = new ReplyDetector();
        $devis = array_values(array_filter($sent, static fn (MailHeader $h) => $h->subject === 'Devis des affiches'))[0];
        $convention = array_values(array_filter($sent, static fn (MailHeader $h) => $h->subject === 'Convention de stage'))[0];

        self::assertNotNull($detector->firstReply($inbox, $devis->messageId), 'F-52 : la réponse de Marc est reconnue par le fil');
        self::assertNull($detector->firstReply($inbox, $convention->messageId), 'F-52 : l’absence automatique ne compte pas');
        $alias = array_values(array_filter($inbox, static fn (MailHeader $h) => str_contains(implode(',', $h->to), 'contact@')));
        self::assertCount(1, $alias);
        self::assertSame('Camille Roux', $alias[0]->fromName);
    }
}
