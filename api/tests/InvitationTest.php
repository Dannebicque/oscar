<?php

namespace App\Tests;

use App\Calendar\InvitationParser;
use App\Entity\CalendarAccount;
use App\Entity\CalendarEvent;
use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Mail\MailHeader;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeCalendarProvider;
use App\Tests\Support\FakeOutgoingMailer;
use App\Tests\Support\FakeReaderFactory;

/** F-25 : invitations iCalendar reçues par mail ; répondre est un clic, la réponse part à l'organisateur, l'événement entre dans l'agenda. */
final class InvitationTest extends ApiTestCase
{
    private const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//FR\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:soutenance-1@iut\r\nSEQUENCE:1\r\nDTSTAMP:20261120T100000Z\r\nDTSTART:20261124T130000Z\r\nDTEND:20261124T140000Z\r\nSUMMARY:Soutenance de stage : M. Lemoine\r\nLOCATION:Salle B12\r\nORGANIZER;CN=Claire Petit:mailto:Claire.Petit@entreprise.fr\r\nATTENDEE;PARTSTAT=NEEDS-ACTION:mailto:david@iut-troyes.fr\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    private Mail $mail;
    private string $token;
    private CalendarAccount $cal;

    protected function setUp(): void
    {
        parent::setUp();
        FakeReaderFactory::$ics = self::ICS;
        $david = $this->user('david');
        $acc = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('david@iut-troyes.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->cal = (new CalendarAccount())->setOwner($david)->setLabel('IUT')->setUrl('https://cal.test/iut')->setUsername('d');
        $this->mail = Mail::fromHeader($acc, new MailHeader('<inv@x>', null, [], 'Claire Petit', 'claire.petit@entreprise.fr', [], 'Invitation', new \DateTimeImmutable('2026-11-20 09:00'), false, 3, 'INBOX'));
        array_map(fn ($e) => $this->em->persist($e), [$acc, $this->cal, $this->mail]);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
    }

    protected function tearDown(): void
    {
        FakeReaderFactory::$ics = null;
        parent::tearDown();
    }

    public function testParserReadsOnlyWhatTheScreenNeedsAndRefusesWhatItCannotAnswer(): void
    {
        $p = new InvitationParser();
        $inv = $p->parse(self::ICS);
        self::assertSame('Soutenance de stage : M. Lemoine', $inv['title']);
        self::assertSame('claire.petit@entreprise.fr', $inv['organizerAddress']);
        self::assertSame('Claire Petit', $inv['organizerName']);
        self::assertSame('Salle B12', $inv['location']);
        self::assertNull($p->parse(str_replace('METHOD:REQUEST', 'METHOD:CANCEL', self::ICS)), 'une annulation n’est pas une invitation à répondre');
        self::assertNull($p->parse(str_replace("ORGANIZER;CN=Claire Petit:mailto:Claire.Petit@entreprise.fr\r\n", '', self::ICS)), 'sans organisateur, on ne peut pas répondre');
        self::assertNull($p->parse('pas du tout un calendrier'));
        $evil = str_replace('Soutenance de stage : M. Lemoine', '<script>alert(1)</script>Réunion', self::ICS);
        self::assertStringNotContainsString('<script>', $p->parse($evil)['title'], 'le texte reçu est nettoyé');
    }

    public function testShowReturnsTheInvitationAndNothingIsSentOrChanged(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        [$st, $r] = $this->call('GET', '/api/mails/'.$this->mail->getId().'/invitation', $this->token);
        self::assertSame(200, $st);
        self::assertSame('Salle B12', $r['invitation']['location']);
        self::assertNull($r['response']);
        self::assertSame([], $smtp->sent, 'lire une invitation n’envoie rien');
    }

    public function testAcceptSendsAReplyToTheOrganizerAndAddsTheEventOnce(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        [$st, $r] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/invitation/reply', $this->token, ['response' => 'accepted', 'calendarId' => $this->cal->getId()]);
        self::assertSame(200, $st);
        self::assertTrue($r['added']);
        $mail = $smtp->sent[0];
        self::assertSame('claire.petit@entreprise.fr', $mail->getTo()[0]->getAddress());
        self::assertStringContainsString('Accepté', $mail->getSubject());
        $att = $mail->getAttachments()[0];
        self::assertStringContainsString('METHOD:REPLY', $att->getBody());
        self::assertStringContainsString('PARTSTAT=ACCEPTED', $att->getBody());
        self::assertCount(1, $this->em->getRepository(CalendarEvent::class)->findBy(['uid' => 'soutenance-1@iut']));
        [, $again] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/invitation/reply', $this->token, ['response' => 'tentative', 'calendarId' => $this->cal->getId()]);
        self::assertFalse($again['added'], 'pas de doublon dans l’agenda');
        self::assertCount(1, $this->em->getRepository(CalendarEvent::class)->findBy(['uid' => 'soutenance-1@iut']));
        [, $s] = $this->call('GET', '/api/mails/'.$this->mail->getId().'/invitation', $this->token);
        self::assertSame('tentative', $s['response']);
    }

    public function testDeclineSendsButAddsNothingAndSomeoneElsesMailIsUnknown(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        [, $r] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/invitation/reply', $this->token, ['response' => 'declined', 'calendarId' => $this->cal->getId()]);
        self::assertFalse($r['added']);
        self::assertCount(1, $smtp->sent);
        self::assertCount(0, $this->em->getRepository(CalendarEvent::class)->findAll());
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame(404, $this->call('GET', '/api/mails/'.$this->mail->getId().'/invitation', $other)[0]);
        self::assertSame(422, $this->call('POST', '/api/mails/'.$this->mail->getId().'/invitation/reply', $this->token, ['response' => 'peut-etre'])[0]);
    }

    public function testAMailerOutageLeavesEverythingUntouched(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        $smtp->fail = true;
        [$st] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/invitation/reply', $this->token, ['response' => 'accepted', 'calendarId' => $this->cal->getId()]);
        self::assertSame(502, $st);
        self::assertCount(0, $this->em->getRepository(CalendarEvent::class)->findAll(), 'rien n’est ajouté si la réponse n’est pas partie');
        $this->em->clear();
        self::assertNull($this->em->find(Mail::class, $this->mail->getId())->getInvitationResponse());
    }
}
