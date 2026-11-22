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

    public function testSendCarriesAttachmentsAndRefusesMoreThanTheAccountLimit(): void
    {
        $smtp = static::getContainer()->get(\App\Tests\Support\FakeOutgoingMailer::class);
        $accountId = $this->em->getRepository(MailAccount::class)->findAll()[0]->getId();
        $this->em->find(MailAccount::class, $accountId)->setAttachmentLimitMb(5);
        $this->em->flush();
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

    public function testAccountBusinessDaysOverridesTheUserSettingWhenComputingTheDueDate(): void
    {
        $account = $this->em->getRepository(MailAccount::class)->findAll()[0];
        $this->call('PUT', '/api/settings', $this->token, ['businessDays' => true]);
        $account->setBusinessDays(false);
        $this->em->flush();
        [$st, $r] = $this->call('POST', '/api/mails/send', $this->token, ['accountId' => $account->getId(), 'to' => ['a@x.fr'], 'subject' => 'Délai', 'body' => 'x', 'followHours' => 24]);
        self::assertSame(201, $st);
        $due = new \DateTimeImmutable($r['suivi']['dueAt']);
        self::assertSame($due->format('Y-m-d'), (new \DateTimeImmutable('+24 hours'))->setTime(0, 0)->format('Y-m-d'), 'heures réelles pour ce compte, même si l’utilisateur compte en jours ouvrés');
    }

    public function testADraftKeepsItsAttachmentsBccAndSenderIdentity(): void
    {
        FakeReaderFactory::$appended = [];
        $account = $this->em->getRepository(MailAccount::class)->findAll()[0];
        $identity = new \App\Entity\Identity($account->getOwner(), $account, 'Direction des études', 'etudes@x.fr', 'secretariat@x.fr');
        $this->em->persist($identity);
        $this->em->flush();
        $file = ['name' => 'convention.pdf', 'type' => 'application/pdf', 'content' => base64_encode('%PDF-1.4 contenu de test')];

        [$st] = $this->call('POST', '/api/mails/draft', $this->token, ['accountId' => $account->getId(), 'identityId' => $identity->getId(), 'to' => ['a@x.fr'], 'bcc' => ['b@x.fr'], 'subject' => 'Avec pièce jointe', 'body' => 'Voir la convention', 'attachments' => [$file]]);
        self::assertSame(201, $st);
        $raw = FakeReaderFactory::$appended[0][1];
        self::assertStringContainsString('multipart/mixed', $raw);
        self::assertStringContainsString('convention.pdf', $raw);
        self::assertStringContainsString('Bcc: b@x.fr', $raw);
        self::assertStringContainsString('etudes@x.fr', $raw);
        self::assertStringContainsString('Reply-To: secretariat@x.fr', $raw);
        self::assertSame('Drafts', FakeReaderFactory::$appended[0][0]);
        self::assertCount(0, static::getContainer()->get(\App\Tests\Support\FakeOutgoingMailer::class)->sent, 'rien n’est envoyé');
    }

    public function testADraftWithoutAttachmentsStaysPlainAndTooBigOrForeignIdentityIsRefused(): void
    {
        FakeReaderFactory::$appended = [];
        $account = $this->em->getRepository(MailAccount::class)->findAll()[0];
        self::assertSame(201, $this->call('POST', '/api/mails/draft', $this->token, ['accountId' => $account->getId(), 'subject' => 'Simple', 'body' => 'x'])[0]);
        self::assertStringNotContainsString('multipart', FakeReaderFactory::$appended[0][1]);

        $account->setAttachmentLimitMb(1);
        $this->em->flush();
        $big = ['name' => 'gros.bin', 'type' => 'x', 'content' => base64_encode(str_repeat('a', 1_300_000))];
        [$st, $e] = $this->call('POST', '/api/mails/draft', $this->token, ['accountId' => $account->getId(), 'subject' => 'Gros', 'body' => 'x', 'attachments' => [$big]]);
        self::assertSame([413, 1], [$st, $e['limitMb']]);
        self::assertCount(1, FakeReaderFactory::$appended, 'rien n’est déposé sur le serveur');
        self::assertSame(422, $this->call('POST', '/api/mails/draft', $this->token, ['accountId' => $account->getId(), 'identityId' => '0190a000-0000-7000-8000-000000000000', 'subject' => 'x', 'body' => 'x'])[0]);
    }

    public function testReopeningADraftReturnsItsCopies(): void
    {
        FakeReaderFactory::$draftRecipients = ['cc' => ['c@x.fr'], 'bcc' => ['b@x.fr']];
        $account = $this->em->getRepository(MailAccount::class)->findAll()[0];
        $draft = Mail::fromHeader($account, new MailHeader('<d@x>', null, [], 'David', 'd@x.fr', ['a@x.fr'], 'Brouillon', new \DateTimeImmutable('2026-11-19 09:00:00'), false, 5, 'Drafts'));
        $this->em->persist($draft);
        $this->em->flush();
        [$st, $r] = $this->call('GET', '/api/mails/'.$draft->getId().'/body', $this->token);
        self::assertSame([200, ['c@x.fr'], ['b@x.fr']], [$st, $r['cc'], $r['bcc']]);
        [, $inbox] = $this->call('GET', '/api/mails/'.$this->mail->getId().'/body', $this->token);
        self::assertArrayNotHasKey('cc', $inbox, 'seuls les brouillons renvoient leurs copies');
    }
}
