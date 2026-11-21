<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Mail\MailHeader;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeReaderFactory;

/** F-16 : archiver, corbeille, retour en boîte de réception. Serveur d'abord, base ensuite, isolation par utilisateur. */
final class MailMoveApiTest extends ApiTestCase
{
    private Mail $mail;
    private string $token;
    private string $other;

    protected function setUp(): void
    {
        parent::setUp();
        FakeReaderFactory::$moved = [];
        FakeReaderFactory::$moveFails = false;
        $david = $this->user('david');
        $account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($account);
        $this->mail = Mail::fromHeader($account, new MailHeader('<m@x>', null, [], 'Sophie', 's@x.fr', [], 'Planning', new \DateTimeImmutable('2026-11-19 09:00:00'), false, 7, 'INBOX'));
        $this->em->persist($this->mail);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
        $this->other = $this->tokenFor($this->user('claire'));
    }

    public function testArchiveMovesOnTheServerThenUpdatesTheRow(): void
    {
        [$st, $r] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Archive']);
        self::assertSame(200, $st);
        self::assertSame('Archive', $r['folder']);
        self::assertSame([['INBOX', 7, 'Archive']], FakeReaderFactory::$moved);
        $this->em->clear();
        $m = $this->em->find(Mail::class, $this->mail->getId());
        self::assertSame('Archive', $m->getFolder());
        self::assertSame(507, $m->getUid(), 'le message a un nouvel UID dans le dossier d’arrivée');
    }

    public function testNothingChangesWhenTheServerFailsAndTheMessageIsNeverDeleted(): void
    {
        FakeReaderFactory::$moveFails = true;
        [$st] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Trash']);
        self::assertSame(502, $st);
        $this->em->clear();
        self::assertSame('INBOX', $this->em->find(Mail::class, $this->mail->getId())->getFolder());
        [$st] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Nulle part']);
        self::assertSame(422, $st);
    }

    public function testSomeoneElsesMailIsUnknown(): void
    {
        [$st] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->other, ['folder' => 'Trash']);
        self::assertSame(404, $st);
        self::assertSame([], FakeReaderFactory::$moved);
    }

    public function testAttachmentsAreListedAndOnlyEverDownloaded(): void
    {
        [$st, $r] = $this->call('GET', '/api/mails/'.$this->mail->getId().'/attachments', $this->token);
        self::assertSame(200, $st);
        self::assertSame('convention.pdf', $r['attachments'][0]['name']);
        $this->client->request('GET', '/api/mails/'.$this->mail->getId().'/attachments/0', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);
        $resp = $this->client->getResponse();
        self::assertSame(200, $resp->getStatusCode());
        self::assertSame('application/octet-stream', $resp->headers->get('Content-Type'), 'type forcé : jamais interprété par le navigateur');
        self::assertSame('nosniff', $resp->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('attachment', (string) $resp->headers->get('Content-Disposition'));
        [$st] = $this->call('GET', '/api/mails/'.$this->mail->getId().'/attachments', $this->other);
        self::assertSame(404, $st);
    }

    public function testSendCarriesAttachmentsAndRefusesMoreThanFiveMegabytes(): void
    {
        $smtp = static::getContainer()->get(\App\Tests\Support\FakeOutgoingMailer::class);
        $accountId = $this->em->getRepository(MailAccount::class)->findAll()[0]->getId();
        [$st] = $this->call('POST', '/api/mails/send', $this->token, ['accountId' => $accountId, 'to' => ['a@x.fr'], 'subject' => 'PJ', 'body' => 'Voir pièce jointe', 'attachments' => [['name' => 'note.txt', 'type' => 'text/plain', 'content' => base64_encode('bonjour')]]]);
        self::assertSame(201, $st);
        $atts = $smtp->sent[0]->getAttachments();
        self::assertCount(1, $atts);
        self::assertSame('note.txt', $atts[0]->getFilename());
        [$st] = $this->call('POST', '/api/mails/send', $this->token, ['accountId' => $accountId, 'to' => ['a@x.fr'], 'subject' => 'Gros', 'body' => 'x', 'attachments' => [['name' => 'big.bin', 'type' => 'x', 'content' => base64_encode(str_repeat('a', 6 * 1024 * 1024))]]]);
        self::assertSame(413, $st);
        self::assertCount(1, $smtp->sent, 'rien n’est parti');
    }

    public function testDraftIsDepositedInDraftsAndNothingIsSent(): void
    {
        FakeReaderFactory::$appended = [];
        $smtp = static::getContainer()->get(\App\Tests\Support\FakeOutgoingMailer::class);
        $accountId = $this->em->getRepository(MailAccount::class)->findAll()[0]->getId();
        [$st] = $this->call('POST', '/api/mails/draft', $this->token, ['accountId' => $accountId, 'to' => [], 'subject' => 'Brouillon', 'body' => 'à finir']);
        self::assertSame(201, $st, 'un brouillon peut être sans destinataire');
        self::assertSame('Drafts', FakeReaderFactory::$appended[0][0]);
        self::assertStringContainsString('Subject: Brouillon', FakeReaderFactory::$appended[0][1]);
        self::assertSame([], $smtp->sent, 'rien n’est envoyé');
        [$st] = $this->call('POST', '/api/mails/draft', $this->other, ['accountId' => $accountId, 'subject' => 'x']);
        self::assertSame(404, $st, 'compte d’un autre utilisateur');
    }
}
