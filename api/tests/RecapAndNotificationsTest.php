<?php

namespace App\Tests;

use App\Entity\CalendarAccount;
use App\Entity\CalendarEvent;
use App\Entity\LogEntry;
use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\RecapLog;
use App\Entity\Suivi;
use App\Entity\Task;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use App\Mail\ConnectionMonitor;
use App\Recap\RecapService;
use App\Recap\RelanceNotifier;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeOutgoingMailer;

final class RecapAndNotificationsTest extends ApiTestCase
{
    private User $david;
    private MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->david = $this->user('david');
        $this->account = (new MailAccount())->setOwner($this->david)->setLabel('IUT')->setAddress('david@iut-troyes.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('david');
        $this->em->persist($this->account);
        $this->em->flush();
    }

    private function suivi(string $subject, array $o = []): Suivi
    {
        $s = (new Suivi())->setOwner($this->david)->setSubject($subject)->setContact('Marc Dubois')->setDirection($o['direction'] ?? SuiviDirection::Waiting)
            ->setStatus($o['status'] ?? SuiviStatus::Waiting)->setDueAt(new \DateTimeImmutable($o['due'] ?? '2026-12-30'))->setRelanceAt(isset($o['relance']) ? new \DateTimeImmutable($o['relance']) : null)
            ->setReplyReceived($o['reply'] ?? false)->setNoRelance($o['no'] ?? false);
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    public function testRelanceNotificationsAreGroupedCappedAndNotDuplicated(): void
    {
        $n = static::getContainer()->get(RelanceNotifier::class);
        $now = new \DateTimeImmutable('2026-11-20 09:00:00');
        $this->suivi('Devis', ['relance' => '2026-11-19']);
        self::assertSame(1, $n->notify($this->david, $now));
        self::assertSame(0, $n->notify($this->david, $now), 'pas de doublon le même jour');

        $this->suivi('Convention', ['relance' => '2026-11-18']);
        $this->suivi('Salle', ['relance' => '2026-11-17']);
        self::assertSame(1, $n->notify($this->david, $now));
        $params = array_map(fn (Notification $x) => $x->getParams()['n'], $this->em->getRepository(Notification::class)->findAll());
        self::assertContains(3, $params, 'regroupées en une entrée « 3 relances à faire »');
    }

    public function testNoNotificationForStoppedAnsweredClosedOrFutureRelances(): void
    {
        $n = static::getContainer()->get(RelanceNotifier::class);
        $this->suivi('Stoppée', ['relance' => '2026-11-10', 'no' => true]);
        $this->suivi('Répondue', ['relance' => '2026-11-10', 'reply' => true]);
        $this->suivi('Close', ['relance' => '2026-11-10', 'status' => SuiviStatus::Done]);
        $this->suivi('Future', ['relance' => '2026-12-10']);
        self::assertSame(0, $n->notify($this->david, new \DateTimeImmutable('2026-11-20')));
    }

    public function testDailyCapLimitsProposedRelances(): void
    {
        $this->em->persist((function () { $s = new UserSettings($this->david); $s->apply(['dailyCap' => 3]); return $s; })());
        $this->em->flush();
        foreach (range(1, 6) as $i) {
            $this->suivi("S$i", ['relance' => '2026-11-1'.$i]);
        }
        static::getContainer()->get(RelanceNotifier::class)->notify($this->david, new \DateTimeImmutable('2026-11-20'));
        self::assertSame(3, $this->em->getRepository(Notification::class)->findAll()[0]->getParams()['n']);
    }

    public function testRecapIsSentOnceAtTheUserTimeAndContainsTheRightThings(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        $recaps = static::getContainer()->get(RecapService::class);
        $cal = (new CalendarAccount())->setOwner($this->david)->setLabel('IUT')->setUrl('https://x/')->setUsername('d');
        $perso = (new CalendarAccount())->setOwner($this->david)->setLabel('Perso')->setUrl('https://y/')->setPersonal(true);
        $this->em->persist($cal);
        $this->em->persist($perso);
        foreach ([[$cal, 'Soutenance', '2026-11-20 14:00'], [$perso, 'Dentiste', '2026-11-20 17:00']] as [$c, $t, $start]) {
            $this->em->persist((new CalendarEvent())->setOwner($this->david)->setCalendar($c)->setTitle($t)->setStart(new \DateTimeImmutable($start, new \DateTimeZone('Europe/Paris')))->setEnd(new \DateTimeImmutable($start.' +1 hour', new \DateTimeZone('Europe/Paris'))));
        }
        $this->em->persist((new Task())->setOwner($this->david)->setTitle('Relire la convention')->setDueAt(new \DateTimeImmutable('2026-11-20')));
        $this->em->persist((new Task())->setOwner($this->david)->setTitle('Plus tard')->setDueAt(new \DateTimeImmutable('2026-12-20')));
        $this->em->persist((new Task())->setOwner($this->david)->setTitle('Proposée par l’IA')->setDueAt(new \DateTimeImmutable('2026-11-20'))->setToConfirm(true));
        $this->suivi('Devis des affiches', ['relance' => '2026-11-19']);
        $this->suivi('Planning', ['direction' => SuiviDirection::ToHandle, 'status' => SuiviStatus::New, 'reply' => true]);
        $this->em->flush();

        // 07:59 à Paris (06:59 UTC) : pas encore. 08:01 : le récap du matin part. Encore 08:05 : rien de plus.
        self::assertSame([], $recaps->dispatchDue($this->david, new \DateTimeImmutable('2026-11-20 06:59:00 UTC')));
        self::assertSame(['morning'], $recaps->dispatchDue($this->david, new \DateTimeImmutable('2026-11-20 07:01:00 UTC')));
        self::assertSame([], $recaps->dispatchDue($this->david, new \DateTimeImmutable('2026-11-20 07:05:00 UTC')), 'aucun récap en double (N-08)');
        self::assertCount(1, $smtp->sent);
        $body = $smtp->sent[0]->getTextBody();
        self::assertStringContainsString('Récap du matin', $smtp->sent[0]->getSubject());
        foreach (['Soutenance', 'Relire la convention', 'Devis des affiches', 'Planning'] as $expected) {
            self::assertStringContainsString($expected, $body);
        }
        foreach (['Dentiste', 'Plus tard', 'Proposée par l’IA'] as $absent) {
            self::assertStringNotContainsString($absent, $body, $absent.' n’a rien à faire dans le récap');
        }
        self::assertSame('auto-generated', $smtp->sent[0]->getHeaders()->get('Auto-Submitted')->getBodyAsString());

        // Le soir : second créneau, indépendant.
        self::assertSame(['evening'], $recaps->dispatchDue($this->david, new \DateTimeImmutable('2026-11-20 17:00:00 UTC')));
        self::assertSame(2, $this->em->getRepository(RecapLog::class)->count([]));
    }

    public function testRecapRespectsEmailSettingLocaleAndPersonalCalendarOptIn(): void
    {
        $smtp = static::getContainer()->get(FakeOutgoingMailer::class);
        $s = new UserSettings($this->david);
        $s->apply(['recapEmail' => false]);
        $this->em->persist($s);
        $this->em->flush();
        static::getContainer()->get(RecapService::class)->dispatchDue($this->david, new \DateTimeImmutable('2026-11-20 08:00:00 UTC'));
        self::assertSame([], $smtp->sent, 'canal e-mail désactivé : rien n’est envoyé, mais le créneau est consommé');

        $s->apply(['recapEmail' => true, 'locale' => 'en']);
        $this->em->flush();
        static::getContainer()->get(RecapService::class)->dispatchDue($this->david, new \DateTimeImmutable('2026-11-20 17:00:00 UTC'));
        self::assertStringContainsString('Evening review', $smtp->sent[0]->getSubject());
    }

    public function testRecapSendFailureIsLoggedForTheUserNotThrown(): void
    {
        static::getContainer()->get(FakeOutgoingMailer::class)->fail = true;
        static::getContainer()->get(RecapService::class)->dispatchDue($this->david, new \DateTimeImmutable('2026-11-20 08:00:00 UTC'));
        $log = $this->em->getRepository(LogEntry::class)->findAll();
        self::assertCount(1, $log);
        self::assertStringContainsString('consultable dans Oscar', $log[0]->getMessage());
    }

    public function testConnectionFailuresAreLoggedAndAlertOnlyAfterRepeatedFailuresOncePerDay(): void
    {
        $m = static::getContainer()->get(ConnectionMonitor::class);
        $now = new \DateTimeImmutable('2026-11-20 10:00:00');
        $m->failure($this->account, 'sync', new \RuntimeException('Authentication failed'), $now);
        $m->failure($this->account, 'sync', new \RuntimeException('Authentication failed'), $now);
        self::assertSame(0, $this->em->getRepository(Notification::class)->count([]), 'pas d’alerte au premier échec (F-79)');
        $m->failure($this->account, 'sync', new \RuntimeException('Authentication failed'), $now);
        $m->failure($this->account, 'sync', new \RuntimeException('Authentication failed'), $now);
        self::assertSame(1, $this->em->getRepository(Notification::class)->count([]), 'une seule alerte par jour');
        $logs = $this->em->getRepository(LogEntry::class)->findAll();
        self::assertCount(4, $logs);
        self::assertStringContainsString('identifiant ou le mot de passe', $logs[0]->getMessage());
        self::assertStringNotContainsString('Authentication failed', $logs[0]->getMessage(), 'jamais la trace technique');

        $m->success($this->account);
        self::assertSame(0, $this->account->getFailureCount());
        self::assertNull($this->account->getLastError());
    }

    public function testConnectionsNotificationsAndLogEndpoints(): void
    {
        $m = static::getContainer()->get(ConnectionMonitor::class);
        $m->success($this->account);
        $t = $this->tokenFor($this->david);
        [, $c] = $this->call('GET', '/api/connections', $t);
        self::assertSame(['mail', 'ai'], array_column($c, 'id'));
        self::assertSame('ok', $c[0]['state']);

        $m->failure($this->account, 'sync', new \RuntimeException('connection refused'));
        [, $c] = $this->call('GET', '/api/connections', $t);
        self::assertSame('error', $c[0]['state']);
        [, $log] = $this->call('GET', '/api/log', $t);
        self::assertSame('sync', $log[0]['type']);

        // Les requêtes précédentes ont réinitialisé le gestionnaire : on repart d'une référence gérée.
        $this->em->persist((new Notification('replyReceived', ['who' => 'Sophie', 'subject' => 'Planning'], 'suivis', 'k1'))->setOwner($this->em->getReference(User::class, $this->david->getId())));
        $this->em->flush();
        [, $list] = $this->call('GET', '/api/notifications', $t);
        self::assertSame(['replyReceived', false], [$list[0]['kind'], $list[0]['read']]);
        self::assertSame(204, $this->call('POST', '/api/notifications/read-all', $t, [])[0]);
        self::assertTrue($this->call('GET', '/api/notifications', $t)[1][0]['read']);

        $other = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/notifications', $other)[1]);
        self::assertSame([], $this->call('GET', '/api/log', $other)[1]);
        self::assertSame(['ai'], array_column($this->call('GET', '/api/connections', $other)[1], 'id'));
    }
}
