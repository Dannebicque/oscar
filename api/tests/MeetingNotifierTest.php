<?php

namespace App\Tests;

use App\Entity\CalendarAccount;
use App\Entity\CalendarEvent;
use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Mail\MailHeader;
use App\Recap\MeetingNotifier;
use App\Tests\Support\ApiTestCase;

/** F-71 : un rappel quelques minutes avant un rendez-vous, avec le nombre d'échanges récents avec les participants. */
final class MeetingNotifierTest extends ApiTestCase
{
    private User $david;
    private CalendarAccount $work;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new \DateTimeImmutable('2026-11-23 09:00:00');
        $this->david = $this->user('david');
        $acc = (new MailAccount())->setOwner($this->david)->setLabel('IUT')->setAddress('david@iut-troyes.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('david');
        $this->work = (new CalendarAccount())->setOwner($this->david)->setLabel('IUT')->setUrl('https://cal.test/')->setUsername('');
        $this->em->persist($acc);
        $this->em->persist($this->work);
        foreach ([['<a@x>', 'claire@iut-troyes.fr', 'INBOX', '-2 days'], ['<b@x>', 'claire@iut-troyes.fr', 'INBOX', '-20 days'], ['<c@x>', 'marc@x.fr', 'INBOX', '-1 days'], ['<d@x>', 'claire@iut-troyes.fr', 'INBOX', '-200 days']] as [$id, $from, $folder, $ago]) {
            $this->em->persist(Mail::fromHeader($acc, new MailHeader($id, null, [], 'X', $from, ['david@iut-troyes.fr'], 'Sujet', $this->now->modify($ago), false, random_int(1, 9999), $folder)));
        }
        $this->em->flush();
    }

    private function event(string $title, string $start, array $attendees = [], ?CalendarAccount $cal = null): CalendarEvent
    {
        $e = (new CalendarEvent())->setOwner($this->david)->setCalendar($cal ?? $this->work)->setTitle($title)->setStart(new \DateTimeImmutable($start))->setEnd((new \DateTimeImmutable($start))->modify('+1 hour'))->setAttendees($attendees);
        $this->em->persist($e);
        $this->em->flush();

        return $e;
    }

    private function notify(): int
    {
        return static::getContainer()->get(MeetingNotifier::class)->notify($this->david, $this->now);
    }

    public function testOneReminderPerMeetingWithTheCountOfRecentExchanges(): void
    {
        $this->event('Point avec Claire', '2026-11-23 09:10:00', [['name' => 'Claire', 'address' => 'claire@iut-troyes.fr'], ['name' => 'David', 'address' => 'david@iut-troyes.fr']]);
        $this->event('Plus tard', '2026-11-23 11:00:00');
        self::assertSame(1, $this->notify(), 'seul le rendez-vous qui commence dans la fenêtre est signalé');
        self::assertSame(0, $this->notify(), 'jamais deux fois pour le même rendez-vous');
        $n = $this->em->getRepository(Notification::class)->findOneBy(['kind' => 'meetingSoon']);
        self::assertSame(['title' => 'Point avec Claire', 'minutes' => 10, 'who' => 'Claire', 'n' => 2], $n->getParams(), 'deux échanges de moins de 90 jours avec Claire, pas celui de 200 jours');
        self::assertSame('agenda', $n->getTo());
    }

    public function testPersonalCalendarIsLeftAloneUnlessAllowedAndLeadTimeCanBeTurnedOff(): void
    {
        $perso = (new CalendarAccount())->setOwner($this->david)->setLabel('Perso')->setUrl('https://p.test/')->setUsername('')->setPersonal(true);
        $this->em->persist($perso);
        $this->event('Dentiste', '2026-11-23 09:05:00', [], $perso);
        self::assertSame(0, $this->notify());

        $s = new UserSettings($this->david);
        $s->apply(['includePersonalCalendar' => true]);
        $this->em->persist($s);
        $this->em->flush();
        self::assertSame(1, $this->notify());

        $this->event('Point', '2026-11-23 09:08:00');
        $s->apply(['meetingLeadMinutes' => 0]);
        $this->em->flush();
        self::assertSame(0, $this->notify(), 'rappel coupé');
    }

    public function testTheLeadTimeSettingIsValidated(): void
    {
        $t = $this->tokenFor($this->david);
        self::assertSame(200, $this->call('PUT', '/api/settings', $t, ['meetingLeadMinutes' => 10])[0]);
        self::assertSame(422, $this->call('PUT', '/api/settings', $t, ['meetingLeadMinutes' => 7])[0]);
    }
}
