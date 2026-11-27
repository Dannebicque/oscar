<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\SlotHold;
use App\Mail\MailHeader;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeOutgoingMailer;
use App\Tests\Support\FakeReaderFactory;

/** F-74 : les créneaux proposés se posent en provisoire, seulement si le message part, liés au mail et au suivi. */
final class SlotHoldTest extends ApiTestCase
{
    private string $token;
    private MailAccount $account;
    private Mail $question;

    protected function setUp(): void
    {
        parent::setUp();
        $david = $this->user('david');
        $this->account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($this->account);
        $this->question = Mail::fromHeader($this->account, new MailHeader('<q@x>', null, [], 'Claire Petit', 'claire@x.fr', [], 'Disponibilités', new \DateTimeImmutable('-1 hour'), false, 3, 'INBOX'));
        $this->em->persist($this->question);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
    }

    private function slot(string $when): array
    {
        $start = new \DateTimeImmutable($when);

        return ['start' => $start->format(\DATE_ATOM), 'end' => $start->modify('+1 hour')->format(\DATE_ATOM)];
    }

    private function send(array $extra): array
    {
        return $this->call('POST', '/api/mails/send', $this->token, $extra + ['accountId' => $this->account->getId(), 'to' => ['claire@x.fr'], 'subject' => 'Re: Disponibilités', 'body' => 'Voici.', 'inReplyToMailId' => $this->question->getId()]);
    }

    public function testNothingIsHeldWithoutAskingOrWhenTheMessageDoesNotLeave(): void
    {
        self::assertSame(201, $this->send([])[0]);
        static::getContainer()->get(FakeOutgoingMailer::class)->fail = true;
        self::assertSame(502, $this->send(['holds' => [$this->slot('+3 days 10:00')]])[0]);
        self::assertSame([], $this->call('GET', '/api/slot-holds', $this->token)[1]);
    }

    public function testHeldSlotsAreTiedToTheMailAndTheFollowUp(): void
    {
        [$st, $sent] = $this->send(['followHours' => 48, 'holds' => [$this->slot('+3 days 10:00'), $this->slot('+4 days 14:00')]]);
        self::assertSame(201, $st);

        [, $holds] = $this->call('GET', '/api/slot-holds', $this->token);
        self::assertCount(2, $holds);
        self::assertSame($holds[0]['groupId'], $holds[1]['groupId']);
        self::assertSame(['Claire Petit', '<q@x>', $sent['messageId'], $sent['suivi']['id']], [$holds[0]['contact'], $holds[0]['repliedToMailId'], $holds[0]['mailId'], $holds[0]['suiviId']]);
        // Rien d'autre ne change dans l'agenda : le provisoire reste dans Oscar.
        self::assertSame([], $this->call('GET', '/api/calendar-events', $this->token)[1]);

        $claire = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/slot-holds', $claire)[1]);
        self::assertSame(404, $this->call('DELETE', '/api/slot-holds/'.$holds[0]['id'], $claire)[0]);
        self::assertSame(404, $this->call('DELETE', '/api/slot-holds/group/'.$holds[0]['groupId'], $claire)[0]);

        // Ce que le navigateur écrit (fractions de seconde, « Z ») est accepté.
        self::assertSame(201, $this->send(['holds' => [['start' => (new \DateTimeImmutable('+9 days'))->format('Y-m-d\T10:00:00.000\Z'), 'end' => (new \DateTimeImmutable('+9 days'))->format('Y-m-d\T11:00:00.000\Z')]]])[0]);
        self::assertCount(3, $this->call('GET', '/api/slot-holds', $this->token)[1]);
        self::assertSame(204, $this->call('DELETE', '/api/slot-holds/'.$holds[0]['id'], $this->token)[0]);
        self::assertCount(2, $this->call('GET', '/api/slot-holds', $this->token)[1]);
        self::assertSame(204, $this->call('DELETE', '/api/slot-holds/group/'.$holds[0]['groupId'], $this->token)[0]);
        self::assertCount(1, $this->call('GET', '/api/slot-holds', $this->token)[1], 'l’autre proposition reste');
    }

    public function testAPastSlotLeavesByItselfAndAScheduledMailHoldsNothingBeforeLeaving(): void
    {
        $david = $this->user('david2');
        $this->em->persist(new SlotHold($david, '0190a000-0000-7000-8000-000000000000', new \DateTimeImmutable('-2 days'), new \DateTimeImmutable('-1 day'), 'x', 'y', '<m@x>'));
        $this->em->flush();
        self::assertSame([], $this->call('GET', '/api/slot-holds', $this->tokenFor($david))[1]);
        $this->em->clear();
        self::assertCount(0, $this->em->getRepository(SlotHold::class)->findAll());

        $mail = ['accountId' => $this->account->getId(), 'to' => ['claire@x.fr'], 'subject' => 's', 'body' => 'b', 'holds' => [$this->slot('+3 days 10:00')]];
        self::assertSame(201, $this->call('POST', '/api/scheduled-mails', $this->token, ['mail' => $mail, 'sendAt' => (new \DateTimeImmutable('+1 day'))->format(\DATE_ATOM)])[0]);
        self::assertSame([], $this->call('GET', '/api/slot-holds', $this->token)[1]);
    }

    public function testASlotAlreadyOfferedIsNotOfferedAgain(): void
    {
        $before = FakeReaderFactory::$body;
        FakeReaderFactory::$body = 'Peux-tu m’indiquer tes disponibilités la semaine prochaine ?';
        try {
            $offered = $this->call('GET', '/api/mails/'.$this->question->getId().'/hints', $this->token)[1]['availability']['slots'];
            self::assertSame(201, $this->send(['holds' => [$offered[0]]])[0]);
            $after = $this->call('GET', '/api/mails/'.$this->question->getId().'/hints', $this->token)[1]['availability']['slots'];
            self::assertNotContains($offered[0]['start'], array_column($after, 'start'));
        } finally {
            FakeReaderFactory::$body = $before;
        }
    }

    public function testBadSlotsAreRefusedBeforeSending(): void
    {
        self::assertSame(422, $this->send(['holds' => [['start' => 'mardi', 'end' => 'mercredi']]])[0]);
    }
}
