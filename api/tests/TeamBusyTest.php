<?php

namespace App\Tests;

use App\Entity\CalendarAccount;
use App\Entity\CalendarEvent;
use App\Entity\SharedMailbox;
use App\Tests\Support\ApiTestCase;

/** F-26 : créneaux occupés de l'équipe, sans détail, seulement pour ceux qui l'acceptent. */
final class TeamBusyTest extends ApiTestCase
{
    public function testOnlyOptedInColleaguesWorkCalendarsAndNeverTitlesOrPersonalEvents(): void
    {
        $david = $this->user('david');
        $claire = $this->user('claire');
        $marc = $this->user('marc');
        $box = new SharedMailbox('contact@iut-troyes.fr', 'Contact', 'contact');
        foreach ([$david, $claire, $marc] as $u) {
            $box->addMember($u);
        }
        $this->em->persist($box);
        $work = (new CalendarAccount())->setOwner($claire)->setLabel('IUT')->setUrl('https://c.test/a')->setUsername('c');
        $perso = (new CalendarAccount())->setOwner($claire)->setLabel('Perso')->setUrl('https://c.test/b')->setUsername('c')->setPersonal(true);
        $marcCal = (new CalendarAccount())->setOwner($marc)->setLabel('IUT')->setUrl('https://c.test/c')->setUsername('m');
        array_map(fn ($a) => $this->em->persist($a), [$work, $perso, $marcCal]);
        $at = static fn (string $d) => new \DateTimeImmutable($d);
        foreach ([[$work, 'Rendez-vous médical confidentiel', '2026-11-24 10:00', '2026-11-24 11:00'], [$perso, 'Dîner', '2026-11-24 19:00', '2026-11-24 21:00'], [$marcCal, 'Jury', '2026-11-24 14:00', '2026-11-24 15:00']] as [$cal, $title, $s, $e]) {
            $this->em->persist((new CalendarEvent())->setOwner($cal->getOwner())->setCalendar($cal)->setTitle($title)->setStart($at($s))->setEnd($at($e)));
        }
        $this->em->flush();
        $this->call('PUT', '/api/settings', $this->tokenFor($claire), ['shareBusy' => true]); // Claire accepte, Marc non
        $t = $this->tokenFor($david);
        [$st, $r] = $this->call('GET', '/api/team/busy?mailbox=contact&from='.urlencode('2026-11-23T00:00:00+01:00').'&to='.urlencode('2026-11-30T00:00:00+01:00'), $t);
        self::assertSame(200, $st);
        self::assertCount(1, $r, 'Claire (agenda pro) seulement : ni son agenda perso, ni Marc qui n’a pas accepté');
        self::assertSame('Claire', $r[0]['member']);
        self::assertSame(['member', 'start', 'end'], array_keys($r[0]), 'aucun titre, lieu ou participant');
        self::assertStringNotContainsString('médical', json_encode($r));
        $outsider = $this->tokenFor($this->user('lea'));
        self::assertSame(404, $this->call('GET', '/api/team/busy?mailbox=contact&from=2026-11-23&to=2026-11-30', $outsider)[0], 'non-membre : inconnu');
        self::assertSame(422, $this->call('GET', '/api/team/busy?mailbox=contact&from=2026-11-23&to=2027-11-30', $t)[0]);
    }
}
