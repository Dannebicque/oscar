<?php

namespace App\Tests;

use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\Suivi;
use App\Entity\User;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use App\Recap\RecapService;
use App\Recap\RelanceNotifier;
use App\Tests\Support\ApiTestCase;

/** Mode congés : relances et récaps en pause, reprise au retour, réglage validé. Rien n'est supprimé ni fermé. */
final class VacationTest extends ApiTestCase
{
    private User $david;

    protected function setUp(): void
    {
        parent::setUp();
        $this->david = $this->user('david');
        $this->em->persist((new MailAccount())->setOwner($this->david)->setLabel('IUT')->setAddress('david@iut-troyes.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('david'));
        $this->em->persist((new Suivi())->setOwner($this->david)->setSubject('Devis')->setContact('Marc')->setDirection(SuiviDirection::Waiting)->setStatus(SuiviStatus::Waiting)
            ->setDueAt(new \DateTimeImmutable('2026-12-30'))->setRelanceAt(new \DateTimeImmutable('2026-11-19')));
        $this->em->flush();
    }

    public function testRelancesAreSilentDuringTheVacationThenComeBack(): void
    {
        $t = $this->tokenFor($this->david);
        self::assertSame(200, $this->call('PUT', '/api/settings', $t, ['vacationFrom' => '2026-11-20', 'vacationTo' => '2026-11-27', 'vacationMessage' => 'Absent.'])[0]);
        $n = static::getContainer()->get(RelanceNotifier::class);
        self::assertSame(0, $n->notify($this->david, new \DateTimeImmutable('2026-11-24 09:00:00')), 'en congés : aucune alerte de relance');
        self::assertSame(0, $this->em->getRepository(Notification::class)->count([]));
        self::assertSame(0, $n->notify($this->david, new \DateTimeImmutable('2026-11-27 20:00:00 UTC')) , 'le dernier jour compte encore');
        self::assertSame(1, $n->notify($this->david, new \DateTimeImmutable('2026-11-28 09:00:00')), 'au retour, la relance est signalée');
        $s = $this->em->getRepository(Suivi::class)->findOneBy(['subject' => 'Devis']);
        self::assertSame(SuiviStatus::Waiting, $s->getStatus(), 'rien n’a été fermé ni déplacé');
    }

    public function testRecapsAreSkippedDuringTheVacation(): void
    {
        $this->call('PUT', '/api/settings', $this->tokenFor($this->david), ['vacationFrom' => '2026-11-20', 'vacationTo' => '2026-11-27']);
        $r = static::getContainer()->get(RecapService::class);
        self::assertSame([], $r->dispatchDue($this->david, new \DateTimeImmutable('2026-11-24 12:00:00 UTC')));
        self::assertNotSame([], $r->dispatchDue($this->david, new \DateTimeImmutable('2026-11-28 12:00:00 UTC')));
    }

    public function testSettingIsValidated(): void
    {
        $t = $this->tokenFor($this->david);
        foreach ([['vacationFrom' => '20/11/2026'], ['vacationFrom' => '2026-02-31'], ['vacationMessage' => str_repeat('x', 2001)], ['vacationFrom' => 5]] as $bad) {
            self::assertSame(422, $this->call('PUT', '/api/settings', $t, $bad)[0], json_encode($bad));
        }
        self::assertSame(422, $this->call('PUT', '/api/settings', $t, ['vacationFrom' => '2026-11-27', 'vacationTo' => '2026-11-20'])[0], 'le retour ne précède pas le départ');
        self::assertSame(200, $this->call('PUT', '/api/settings', $t, ['vacationFrom' => '', 'vacationTo' => ''])[0], 'vider les dates arrête le mode');
    }
}
