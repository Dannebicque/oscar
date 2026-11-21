<?php

namespace App\Tests;

use App\Entity\Notification;
use App\Notification\NotificationCenter;
use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/** F-75 : un canal par type de notification ; une seule alerte par fait, sur chaque canal (N-08). */
final class NotificationChannelsTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    private function center(): NotificationCenter
    {
        return static::getContainer()->get(NotificationCenter::class);
    }

    public function testDefaultsAreInAppAndDesktopWithEmailOnlyForConnectionFailures(): void
    {
        $u = $this->user('david');
        $c = $this->center()->channelsFor($u);
        self::assertSame(['app' => true, 'desktop' => true, 'email' => false], $c['replyReceived']);
        self::assertTrue($c['connectionFailed']['email']);
    }

    public function testEmailChannelSendsOnceAndAppChannelOffKeepsNoRowInThePanel(): void
    {
        $u = $this->user('david');
        $u->setEmail('david@iut-troyes.fr');
        $this->em->flush();
        $t = $this->tokenFor($u);
        [$st] = $this->call('PUT', '/api/settings', $t, ['notifyChannels' => ['replyReceived' => ['app' => false, 'desktop' => false, 'email' => true]]]);
        self::assertSame(200, $st);
        $n = static fn () => new Notification('replyReceived', ['who' => 'Sophie', 'subject' => 'Planning'], 'suivis', 'reply:1');
        self::assertTrue($this->center()->deliver($u, $n(), true));
        self::assertFalse($this->center()->deliver($u, $n(), true), 'pas de doublon, même sans ligne en base');
        self::assertEmailCount(1);
        $mail = self::getMailerMessage();
        self::assertStringContainsString('Sophie', $mail->getTextBody());
        self::assertSame('david@iut-troyes.fr', $mail->getTo()[0]->getAddress());
        self::assertCount(0, $this->em->getRepository(Notification::class)->findAll(), 'canal application coupé : rien dans le panneau');
    }

    public function testAppAndDesktopFlagsFollowTheChoice(): void
    {
        $u = $this->user('david');
        $t = $this->tokenFor($u);
        $this->call('PUT', '/api/settings', $t, ['notifyChannels' => ['relanceDue' => ['app' => true, 'desktop' => false, 'email' => false]]]);
        $this->center()->deliver($u, new Notification('relanceDue', ['n' => 1, 'who' => 'X', 'subject' => 'Y'], 'suivis', 'relance:1'), true);
        $row = $this->em->getRepository(Notification::class)->findAll()[0];
        self::assertTrue($row->isInApp());
        self::assertFalse($row->isDesktop());
    }

    public function testInvalidChannelSettingsAreRefused(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [$st] = $this->call('PUT', '/api/settings', $t, ['notifyChannels' => ['replyReceived' => ['email' => 'oui']]]);
        self::assertSame(422, $st);
        [$st] = $this->call('PUT', '/api/settings', $t, ['notifyChannels' => 'tout']);
        self::assertSame(422, $st);
    }

    public function testDirectoryIsOffByDefaultAndTheUserChoosesOptionalFields(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [$st, $r] = $this->call('GET', '/api/directory/status', $t);
        self::assertSame([200, false], [$st, $r['configured']]);
        [$st, $r] = $this->call('GET', '/api/directory?q=laurent', $t);
        self::assertSame([200, []], [$st, $r]);
        [, $s] = $this->call('PUT', '/api/settings', $t, ['dirShowPhone' => true]);
        self::assertTrue($s['dirShowPhone']);
        self::assertTrue($s['dirShowDepartment']);
    }
}
