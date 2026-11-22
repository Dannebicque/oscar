<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\Suivi;
use App\Mail\MailHeader;
use App\Tests\Support\FakeOutgoingMailer;
use App\Tests\Support\FakeReaderFactory;
use App\Tests\Support\ApiTestCase;

final class SendMailTest extends ApiTestCase
{
    private FakeOutgoingMailer $smtp;
    private string $token;
    private MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        FakeReaderFactory::$appendFails = false;
        FakeReaderFactory::$appended = [];
        $david = $this->user('david');
        $this->account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('david@iut-troyes.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('david');
        $this->em->persist($this->account);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
    }

    private function payload(array $over = []): array
    {
        return $over + ['accountId' => $this->account->getId(), 'to' => ['marc.dubois@imprimerie.fr'], 'subject' => 'Devis des affiches', 'body' => 'Bonjour Marc'];
    }

    public function testSendingWithFollowCreatesTheSuiviAtSendTime(): void
    {
        [$st, $r] = $this->call('POST', '/api/mails/send', $this->token, $this->payload(['followHours' => 48, 'followSuggestedByAi' => true]));
        self::assertSame(201, $st);
        self::assertCount(1, $this->smtp->sent);
        self::assertTrue($r['savedToSent']);
        self::assertSame('Sent', FakeReaderFactory::$appended[0][0]);

        $this->em->clear();
        $s = $this->em->getRepository(Suivi::class)->findOneBy(['subject' => 'Devis des affiches']);
        self::assertSame($r['messageId'], $s->getMailId(), 'le suivi pointe sur le Message-ID réellement envoyé');
        self::assertSame('waiting', $s->getDirection()->value);
        self::assertTrue($s->isSuggestedByAi());
        self::assertSame('Marc Dubois', $s->getContact());
        self::assertSame(['created', 'sent'], array_column($s->getJournal(), 'action'));
        self::assertEquals($s->getDueAt(), $s->getRelanceAt());
        self::assertStringContainsString(trim($r['messageId'], '<>'), $this->smtp->sent[0]->getHeaders()->get('Message-ID')->getBodyAsString());
    }

    public function testSendingWithoutFollowCreatesNoSuivi(): void
    {
        self::assertSame(201, $this->call('POST', '/api/mails/send', $this->token, $this->payload())[0]);
        self::assertSame(0, $this->em->getRepository(Suivi::class)->count([]));
    }

    public function testAFailedSendCreatesNothingAndSaysSo(): void
    {
        $this->smtp->fail = true;
        [$st, $r] = $this->call('POST', '/api/mails/send', $this->token, $this->payload(['followHours' => 48]));
        self::assertSame(502, $st);
        self::assertStringContainsString('Rien n’a été créé', $r['title']);
        self::assertSame(0, $this->em->getRepository(Suivi::class)->count([]));
        self::assertSame([], FakeReaderFactory::$appended);
    }

    public function testAFailedCopyToSentDoesNotHideThatTheMessageWasSent(): void
    {
        FakeReaderFactory::$appendFails = true;
        [$st, $r] = $this->call('POST', '/api/mails/send', $this->token, $this->payload(['followHours' => 24]));
        self::assertSame(201, $st);
        self::assertFalse($r['savedToSent']);
        self::assertCount(1, $this->smtp->sent);
        self::assertSame(1, $this->em->getRepository(Suivi::class)->count([]));
    }

    public function testReplyKeepsTheThreadHeaders(): void
    {
        $orig = Mail::fromHeader($this->account, new MailHeader('<orig@x>', null, ['<root@x>'], 'Sophie', 'sophie@x.fr', [], 'Planning', new \DateTimeImmutable(), false, 7));
        $this->em->persist($orig);
        $this->em->flush();
        $this->call('POST', '/api/mails/send', $this->token, $this->payload(['inReplyToMailId' => $orig->getId()]));
        $h = $this->smtp->sent[0]->getHeaders();
        self::assertSame('<orig@x>', $h->get('In-Reply-To')->getBodyAsString());
        self::assertSame('<root@x> <orig@x>', $h->get('References')->getBodyAsString());
    }

    public function testValidationAndOwnership(): void
    {
        self::assertSame(422, $this->call('POST', '/api/mails/send', $this->token, $this->payload(['to' => []]))[0]);
        self::assertSame(422, $this->call('POST', '/api/mails/send', $this->token, $this->payload(['to' => ['pas-un-mail']]))[0]);
        self::assertSame(422, $this->call('POST', '/api/mails/send', $this->token, $this->payload(['subject' => '']))[0]);
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame(404, $this->call('POST', '/api/mails/send', $other, $this->payload())[0], 'pas d’envoi depuis le compte d’un autre');
        self::assertSame([], $this->smtp->sent);
        self::assertSame(401, $this->call('POST', '/api/mails/send', null, $this->payload())[0]);
    }

    public function testSmtpDsnEncodesCredentialsAndHandlesEncryption(): void
    {
        $vault = static::getContainer()->get(\App\Security\CredentialVault::class);
        $a = (new MailAccount())->setSmtpHost('smtp.x.fr')->setSmtpPort(465)->setSmtpEncryption('ssl')->setUsername('d@x.fr')->setPasswordEncrypted($vault->encrypt('p@ss/w:rd'));
        $m = new \App\Mail\SymfonyOutgoingMailer($vault);
        self::assertSame('smtps://d%40x.fr:p%40ss%2Fw%3Ard@smtp.x.fr:465', $m->dsn($a));
        self::assertStringContainsString('auto_tls=false', $m->dsn($a->setSmtpEncryption('none')->setSmtpPort(1025)));
    }

    public function testAttachmentsAreLimitedPerAccountWithTenMegabytesByDefault(): void
    {
        self::assertSame(10 * 1024 * 1024, $this->account->attachmentLimitBytes(), 'défaut : 10 Mo');
        $file = ['name' => 'a.bin', 'type' => 'application/octet-stream', 'content' => base64_encode(str_repeat('a', 1_300_000))]; // 1,3 Mo

        $this->em->find(MailAccount::class, $this->account->getId())->setAttachmentLimitMb(1);
        $this->em->flush();
        [$st, $e] = $this->call('POST', '/api/mails/send', $this->token, $this->payload(['attachments' => [$file]]));
        self::assertSame([413, 1], [$st, $e['limitMb']], 'la limite du compte s’applique');
        self::assertStringContainsString('1 Mo', $e['title']);

        $this->em->find(MailAccount::class, $this->account->getId())->setAttachmentLimitMb(3);
        $this->em->flush();
        self::assertSame(201, $this->call('POST', '/api/mails/send', $this->token, $this->payload(['attachments' => [$file, $file]]))[0], '2,6 Mo sous 3 Mo');
        self::assertSame(413, $this->call('POST', '/api/mails/send', $this->token, $this->payload(['attachments' => [$file, $file, $file]]))[0], '3,9 Mo au-dessus de 3 Mo');
    }
}
