<?php

namespace App\Tests;

use App\Entity\CalendarAccount;
use App\Entity\LogEntry;
use App\Entity\MailAccount;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeCalendarProvider;

final class SyncTest extends ApiTestCase
{
    public function testSyncNowReportsEachAccountAndUpdatesTheConnectionStates(): void
    {
        $david = $this->user('david');
        $ok = (new MailAccount())->setOwner($david)->setLabel('Bonne boîte')->setAddress('a@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('a');
        $ko = (new MailAccount())->setOwner($david)->setLabel('Boîte en panne')->setAddress('b@x.fr')->setImapHost('h')->setImapPort(1)->setSmtpHost('h')->setUsername('b');
        $cal = (new CalendarAccount())->setOwner($david)->setLabel('IUT')->setUrl('https://x/');
        foreach ([$ok, $ko, $cal] as $e) {
            $this->em->persist($e);
        }
        $this->em->flush();
        $t = $this->tokenFor($david);

        [$st, $r] = $this->call('POST', '/api/sync', $t, []);
        self::assertSame(200, $st);
        self::assertSame([['mail', 'Bonne boîte', true], ['mail', 'Boîte en panne', false], ['calendar', 'IUT', true]], array_map(fn ($x) => [$x['kind'], $x['account'], $x['ok']], $r['results']));
        [, $conns] = $this->call('GET', '/api/connections', $t);
        self::assertSame('error', $conns[0]['state'], 'une boîte en panne suffit à signaler la messagerie');
        self::assertSame('ok', $conns[1]['state']);
        self::assertNotNull($conns[1]['lastOk']);

        static::getContainer()->get(FakeCalendarProvider::class)->fail = true;
        $this->call('POST', '/api/sync', $t, []);
        self::assertSame('error', $this->call('GET', '/api/connections', $t)[1][1]['state']);
        self::assertGreaterThanOrEqual(2, $this->em->getRepository(LogEntry::class)->count([]));
        self::assertSame([], $this->call('POST', '/api/sync', $this->tokenFor($this->user('claire')), [])[1]['results'], 'chacun ne synchronise que ses comptes');
    }
}
