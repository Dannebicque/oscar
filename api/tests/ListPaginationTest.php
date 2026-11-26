<?php

namespace App\Tests;

use App\Entity\Task;
use App\Tests\Support\ApiTestCase;

/** Les listes ne s'arrêtent pas à 30 éléments : pages de 500, lues toutes par le front (F-31). */
final class ListPaginationTest extends ApiTestCase
{
    public function testListsArePagedByFiveHundred(): void
    {
        $user = $this->user('david');
        $t = $this->tokenFor($user);
        for ($i = 0; $i < 503; ++$i) {
            $this->em->persist((new Task())->setOwner($user)->setTitle('Tâche '.$i));
        }
        $this->em->flush();

        self::assertCount(500, $this->call('GET', '/api/tasks', $t)[1]);
        self::assertCount(3, $this->call('GET', '/api/tasks?page=2', $t)[1]);
        self::assertCount(0, $this->call('GET', '/api/tasks?page=3', $t)[1]);
    }
}
