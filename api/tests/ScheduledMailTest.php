<?php

namespace App\Tests;

use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\ScheduledMail;
use App\Mail\SendDueMailsHandler;
use App\Mail\SendDueMailsMessage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeOutgoingMailer;
use App\Tests\Support\FakeReaderFactory;

/** F-73 : un mail programmé part à l'heure choisie par l'utilisateur, jamais avant, et peut être annulé. */
final class ScheduledMailTest extends ApiTestCase
{
    private string $token;
    private MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        FakeReaderFactory::$appendFails = false;
        FakeReaderFactory::$appended = [];
        $david = $this->user('david');
        $this->account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('david@iut-troyes.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('david');
        $this->em->persist($this->account);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
    }

    private function body(string $at, array $over = []): array
    {
        return ['mail' => $over + ['accountId' => $this->account->getId(), 'to' => ['marc@imprimerie.fr'], 'subject' => 'Devis', 'body' => 'Bonjour', 'followHours' => 48], 'sendAt' => $at];
    }

    private function runDue(): int
    {
        return static::getContainer()->get(SendDueMailsHandler::class)(new SendDueMailsMessage());
    }

    public function testNothingLeavesBeforeTheChosenTimeThenItIsSentWithItsSuivi(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        [$st, $r] = $this->call('POST', '/api/scheduled-mails', $this->token, $this->body((new \DateTimeImmutable('+2 hours'))->format(\DATE_ATOM)));
        self::assertSame(201, $st);
        self::assertSame(0, $this->runDue());
        self::assertCount(0, $smtp->sent, 'rien ne part avant l’heure choisie');
        self::assertCount(1, $this->call('GET', '/api/scheduled-mails', $this->token)[1]);

        $row = $this->em->find(ScheduledMail::class, $r['id']);
        (new \ReflectionProperty($row, 'sendAt'))->setValue($row, new \DateTimeImmutable('-1 minute'));
        $this->em->flush();
        self::assertSame(1, $this->runDue());
        self::assertSame(0, $this->runDue(), 'jamais deux fois');
        self::assertCount(1, $smtp->sent);
        self::assertSame([], $this->call('GET', '/api/scheduled-mails', $this->token)[1]);
        $this->em->clear();
        self::assertSame(1, $this->em->getRepository(\App\Entity\Suivi::class)->count(['subject' => 'Devis']), 'le suivi naît à l’envoi réel');
    }

    public function testCancelReturnsTheMessageAndNothingIsSent(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        [, $r] = $this->call('POST', '/api/scheduled-mails', $this->token, $this->body((new \DateTimeImmutable('+1 day'))->format(\DATE_ATOM)));
        [$st, $back] = $this->call('DELETE', '/api/scheduled-mails/'.$r['id'], $this->token);
        self::assertSame(200, $st);
        self::assertSame('Devis', $back['subject']);
        self::assertSame(0, $this->runDue());
        self::assertCount(0, $smtp->sent);
        self::assertSame(404, $this->call('DELETE', '/api/scheduled-mails/'.$r['id'], $this->token)[0]);
    }

    public function testValidationIsolationAndFailureNotice(): void
    {
        self::assertSame(422, $this->call('POST', '/api/scheduled-mails', $this->token, $this->body((new \DateTimeImmutable('-3 days'))->format(\DATE_ATOM)))[0]);
        self::assertSame(422, $this->call('POST', '/api/scheduled-mails', $this->token, $this->body((new \DateTimeImmutable('+200 days'))->format(\DATE_ATOM)))[0]);
        self::assertSame(404, $this->call('POST', '/api/scheduled-mails', $this->token, $this->body((new \DateTimeImmutable('+1 day'))->format(\DATE_ATOM), ['accountId' => '0190a000-0000-7000-8000-000000000000']))[0]);

        [, $r] = $this->call('POST', '/api/scheduled-mails', $this->token, $this->body((new \DateTimeImmutable('+1 day'))->format(\DATE_ATOM)));
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/scheduled-mails', $other)[1]);
        self::assertSame(404, $this->call('DELETE', '/api/scheduled-mails/'.$r['id'], $other)[0]);

        static::getContainer()->get(FakeOutgoingMailer::class)->fail = true;
        $row = $this->em->find(ScheduledMail::class, $r['id']);
        (new \ReflectionProperty($row, 'sendAt'))->setValue($row, new \DateTimeImmutable('-1 minute'));
        $this->em->flush();
        self::assertSame(0, $this->runDue());
        $this->em->clear();
        self::assertSame('failed', $this->em->find(ScheduledMail::class, $r['id'])->getStatus());
        self::assertSame(1, $this->em->getRepository(Notification::class)->count(['kind' => 'scheduledFailed']));
    }
}
