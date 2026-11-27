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

    public function testReadFlagGoesBothWays(): void
    {
        $inbox = $this->reader->fetchAfter('INBOX', 0);
        $uid = $inbox[0]->uid;
        $this->reader->setSeen('INBOX', $uid, false);
        self::assertContains($uid, $this->reader->unseenUids('INBOX'));
        self::assertFalse($this->reader->fetchAfter('INBOX', $uid - 1)[0]->seen);

        $this->reader->setSeen('INBOX', $uid, true);
        self::assertNotContains($uid, $this->reader->unseenUids('INBOX'));
        self::assertTrue($this->reader->fetchAfter('INBOX', $uid - 1)[0]->seen);
        self::assertNotNull($this->reader->fetchBody('INBOX', $uid));
    }

    public function testListsTheMessagesStillOnTheServer(): void
    {
        $uids = array_map(static fn (MailHeader $h) => $h->uid, $this->reader->fetchAfter('INBOX', 0));
        self::assertSame($uids, array_values(array_intersect($this->reader->uids('INBOX'), $uids)));
        self::assertCount(\count($uids), $this->reader->uids('INBOX'));
        $this->expectException(\RuntimeException::class);
        $this->reader->uids('Dossier-qui-n-existe-pas');
    }

    public function testReadsTheFormattedMessageWithItsInlineImage(): void
    {
        $letters = array_values(array_filter($this->reader->fetchAfter('INBOX', 0), static fn (MailHeader $h) => $h->subject === 'Lettre d\'information de novembre'));
        self::assertNotSame([], $letters, 'recharger les mails de test : python3 docker/dovecot/seed.py --reset');
        $content = $this->reader->fetchContent('INBOX', $letters[0]->uid);

        self::assertStringContainsString('portes ouvertes', $content->text);
        self::assertStringContainsString('<table', (string) $content->html);
        self::assertSame('image/png', $content->inline['logo@iut']['type'] ?? null);
        $out = (new \App\Mail\MailHtml())->render((string) $content->html, $content->inline);
        self::assertStringContainsString('src="data:image/png;base64,', $out['html']);
        self::assertSame(1, $out['blockedImages']);
        self::assertStringNotContainsString('<script', $out['html']);
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
