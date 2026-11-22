<?php

namespace App\Tests;

use App\Entity\Suivi;
use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/** L'historique de démonstration nourrit le bilan des suivis ; il ne s'insère qu'une fois. */
final class SeedHistoryTest extends ApiTestCase
{
    public function testHistoryIsInsertedOnceWithSentAndReplyEntries(): void
    {
        $this->user('david');
        $cmd = (new Application(static::getContainer()->get('kernel')))->find('app:suivis:seed');
        $t = new CommandTester($cmd);
        self::assertSame(0, $t->execute(['--history' => true]));
        $n = $this->em->getRepository(Suivi::class)->count([]);
        self::assertGreaterThan(10, $n);
        self::assertSame(0, (new CommandTester($cmd))->execute(['--history' => true]));
        self::assertSame($n, $this->em->getRepository(Suivi::class)->count([]), 'pas de doublon au second passage');
        $this->em->clear();
        $answered = array_filter($this->em->getRepository(Suivi::class)->findAll(), fn (Suivi $s) => \in_array('replyReceived', array_column($s->getJournal(), 'action'), true));
        self::assertNotEmpty($answered);
    }
}
