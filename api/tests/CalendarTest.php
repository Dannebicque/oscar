<?php

namespace App\Tests;

use App\Calendar\CalDavProvider;
use App\Calendar\CalendarEventData;
use App\Calendar\CalendarSynchronizer;
use App\Entity\CalendarAccount;
use App\Entity\CalendarEvent;
use App\Security\CredentialVault;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeCalendarProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CalendarTest extends ApiTestCase
{
    private const NOW = '2026-11-20 10:00:00';

    private function account(string $owner = 'david'): CalendarAccount
    {
        $a = (new CalendarAccount())->setOwner($this->user($owner))->setLabel('IUT')->setUrl('https://cal.test/dav/david/')->setUsername('david');
        $this->em->persist($a);
        $this->em->flush();

        return $a;
    }

    private static function ev(string $uid, string $title, string $start, ?string $end = null): CalendarEventData
    {
        return new CalendarEventData($uid, $title, new \DateTimeImmutable($start), new \DateTimeImmutable($end ?? $start.' +1 hour'));
    }

    public function testSyncAddsUpdatesAndRemovesWithinTheWindow(): void
    {
        $fake = static::getContainer()->get(FakeCalendarProvider::class);
        $acc = $this->account();
        $sync = static::getContainer()->get(CalendarSynchronizer::class);
        $now = new \DateTimeImmutable(self::NOW);

        $fake->remote = [self::ev('a', 'Point équipe', '2026-11-21 09:00'), self::ev('b', 'Jury', '2026-11-22 14:00')];
        self::assertSame(['added' => 2, 'updated' => 0, 'removed' => 0], $sync->sync($acc, $now));
        self::assertSame(['added' => 0, 'updated' => 0, 'removed' => 0], $sync->sync($acc, $now), 'idempotent');

        $fake->remote = [self::ev('a', 'Point équipe (déplacé)', '2026-11-21 10:00')];
        self::assertSame(['added' => 0, 'updated' => 1, 'removed' => 1], $sync->sync($acc, $now));
        self::assertSame(['Point équipe (déplacé)'], array_map(fn (CalendarEvent $e) => $e->getTitle(), $this->em->getRepository(CalendarEvent::class)->findAll()));
    }

    public function testCreateWritesToTheServerFirstAndKeepsNothingIfItRefuses(): void
    {
        $fake = static::getContainer()->get(FakeCalendarProvider::class);
        $acc = $this->account();
        $t = $this->tokenFor($acc->getOwner());
        $body = ['calendarId' => $acc->getId(), 'title' => 'Préparation du jury', 'start' => '2026-11-26T10:00:00+01:00', 'end' => '2026-11-26T11:00:00+01:00', 'location' => 'B104'];

        [$st, $c] = $this->call('POST', '/api/calendar-events', $t, $body);
        self::assertSame(201, $st);
        self::assertCount(1, $fake->created);
        self::assertSame('B104', $fake->created[0]->location);
        self::assertSame($acc->getId(), $c['calendarId']);

        $fake->fail = true;
        [$st, $r] = $this->call('POST', '/api/calendar-events', $t, $body);
        self::assertSame(502, $st);
        self::assertStringContainsString('Rien n’a été ajouté', $r['title']);
        self::assertSame(1, $this->em->getRepository(CalendarEvent::class)->count([]));
    }

    public function testCreateValidationAndOwnership(): void
    {
        $acc = $this->account();
        $t = $this->tokenFor($acc->getOwner());
        self::assertSame(422, $this->call('POST', '/api/calendar-events', $t, ['calendarId' => $acc->getId(), 'title' => 'x', 'start' => '2026-11-26T11:00:00Z', 'end' => '2026-11-26T10:00:00Z'])[0], 'fin avant début');
        self::assertSame(422, $this->call('POST', '/api/calendar-events', $t, ['calendarId' => $acc->getId(), 'title' => '', 'start' => '2026-11-26T10:00:00Z', 'end' => '2026-11-26T11:00:00Z'])[0]);
        self::assertSame(422, $this->call('POST', '/api/calendar-events', $t, ['calendarId' => $acc->getId(), 'title' => 'x', 'start' => 'n’importe quoi', 'end' => '2026-11-26T11:00:00Z'])[0]);
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame(404, $this->call('POST', '/api/calendar-events', $other, ['calendarId' => $acc->getId(), 'title' => 'x', 'start' => '2026-11-26T10:00:00Z', 'end' => '2026-11-26T11:00:00Z'])[0]);
        self::assertSame([], $this->call('GET', '/api/calendar-events', $other)[1]);
    }

    public function testCalDavParsesEventsAndExpandsRecurrence(): void
    {
        $ics1 = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//t//EN\r\nBEGIN:VEVENT\r\nUID:one@x\r\nDTSTAMP:20261101T000000Z\r\nDTSTART:20261123T090000Z\r\nDTEND:20261123T100000Z\r\nSUMMARY:Soutenance\r\nLOCATION:B104\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $ics2 = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//t//EN\r\nBEGIN:VEVENT\r\nUID:weekly@x\r\nDTSTAMP:20261101T000000Z\r\nDTSTART:20261124T080000Z\r\nDTEND:20261124T083000Z\r\nRRULE:FREQ=DAILY;COUNT=3\r\nSUMMARY:Point\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $xml = '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">'
            .'<d:response><d:propstat><d:prop><c:calendar-data>'.htmlspecialchars($ics1).'</c:calendar-data></d:prop></d:propstat></d:response>'
            .'<d:response><d:propstat><d:prop><c:calendar-data>'.htmlspecialchars($ics2).'</c:calendar-data></d:prop></d:propstat></d:response>'
            .'<d:response><d:propstat><d:prop><c:calendar-data>pas de l’iCalendar</c:calendar-data></d:prop></d:propstat></d:response></d:multistatus>';
        $p = new CalDavProvider(new MockHttpClient(new MockResponse($xml, ['http_code' => 207])), new CredentialVault('base64:'.base64_encode(str_repeat('k', 32))));
        $acc = (new CalendarAccount())->setUrl('https://cal.test/')->setUsername('');
        $events = $p->events($acc, new \DateTimeImmutable('2026-11-20'), new \DateTimeImmutable('2026-12-31'));
        $titles = array_map(fn ($e) => $e->title.'@'.$e->start->setTimezone(new \DateTimeZone('UTC'))->format('md'), $events);
        sort($titles);
        self::assertSame(['Point@1124', 'Point@1125', 'Point@1126', 'Soutenance@1123'], $titles, 'récurrence développée, élément illisible ignoré');
        self::assertSame('B104', array_values(array_filter($events, fn ($e) => $e->title === 'Soutenance'))[0]->location);
    }

    public function testCalDavServerErrorsAreReported(): void
    {
        $p = new CalDavProvider(new MockHttpClient(new MockResponse('', ['http_code' => 401])), new CredentialVault('base64:'.base64_encode(str_repeat('k', 32))));
        $this->expectException(\RuntimeException::class);
        $p->events((new CalendarAccount())->setUrl('https://cal.test/'), new \DateTimeImmutable('2026-11-20'), new \DateTimeImmutable('2026-12-01'));
    }

    public function testCalDavPutSendsAnIcsWithoutOverwriting(): void
    {
        $seen = null;
        $client = new MockHttpClient(function (string $method, string $url, array $o) use (&$seen) {
            $seen = [$method, $url, $o['headers'], $o['body']];

            return new MockResponse('', ['http_code' => 201]);
        });
        (new CalDavProvider($client, new CredentialVault('base64:'.base64_encode(str_repeat('k', 32)))))->create((new CalendarAccount())->setUrl('https://cal.test/dav/'), self::ev('uid-1@oscar', 'Jury', '2026-11-26 10:00'));
        self::assertSame('PUT', $seen[0]);
        self::assertSame('https://cal.test/dav/uid-1%40oscar.ics', $seen[1]);
        self::assertContains('If-None-Match: *', $seen[2]);
        self::assertStringContainsString('SUMMARY:Jury', str_replace("\r\n", "\n", $seen[3]));
    }
}
