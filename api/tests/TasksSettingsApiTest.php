<?php

namespace App\Tests;

use App\Entity\Task;
use App\Tests\Support\ApiTestCase;

final class TasksSettingsApiTest extends ApiTestCase
{
    public function testTaskCrudAndIsolation(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [$st, $c] = $this->call('POST', '/api/tasks', $t, ['title' => 'Réserver les salles', 'dueAt' => '2026-11-27', 'priority' => 2, 'project' => 'Jury', 'tags' => ['salles', 'salles', 'x'], 'origin' => 'mail', 'source' => 'Planning']);
        self::assertSame(201, $st);
        self::assertEqualsCanonicalizing(['title' => 'Réserver les salles', 'dueAt' => '2026-11-27', 'priority' => 2, 'tags' => ['salles', 'x'], 'origin' => 'mail', 'done' => false, 'toConfirm' => false], array_intersect_key($c, array_flip(['title', 'dueAt', 'priority', 'tags', 'origin', 'done', 'toConfirm'])));

        self::assertSame(200, $this->call('PATCH', '/api/tasks/'.$c['id'], $t, ['done' => true], 'application/merge-patch+json')[0]);
        self::assertTrue($this->call('GET', '/api/tasks/'.$c['id'], $t)[1]['done']);

        $other = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/tasks', $other)[1]);
        self::assertSame(404, $this->call('PATCH', '/api/tasks/'.$c['id'], $other, ['done' => false], 'application/merge-patch+json')[0]);
        self::assertSame(404, $this->call('DELETE', '/api/tasks/'.$c['id'], $other)[0]);
        self::assertSame(204, $this->call('DELETE', '/api/tasks/'.$c['id'], $t)[0]);
        self::assertSame(0, $this->em->getRepository(Task::class)->count([]));
    }

    public function testTaskValidation(): void
    {
        $t = $this->tokenFor($this->user('david'));
        self::assertSame(422, $this->call('POST', '/api/tasks', $t, ['title' => ''])[0]);
        self::assertSame(422, $this->call('POST', '/api/tasks', $t, ['title' => 'x', 'priority' => 9])[0]);
        self::assertSame(400, $this->call('POST', '/api/tasks', $t, ['title' => 'x', 'origin' => 'magie'])[0]);
    }

    public function testAiProposedTaskIsMarkedToConfirm(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [, $c] = $this->call('POST', '/api/tasks', $t, ['title' => 'Confirmer le nombre de participants', 'origin' => 'ai', 'toConfirm' => true]);
        self::assertTrue($c['toConfirm']);
        $this->call('PATCH', '/api/tasks/'.$c['id'], $t, ['toConfirm' => false], 'application/merge-patch+json');
        self::assertFalse($this->call('GET', '/api/tasks/'.$c['id'], $t)[1]['toConfirm']);
    }

    public function testSettingsDefaultsValidationAndPersistence(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [$st0, $s] = $this->call('GET', '/api/settings', $t);
        self::assertSame(200, $st0);
        self::assertSame([48, 5, 'university', true], [$s['relanceHours'], $s['dailyCap'], $s['aiMode'], $s['businessDays']]);
        self::assertSame(200, $this->call('PUT', '/api/settings', $t, ['relanceHours' => 72, 'aiMode' => 'off', 'recapMorning' => '07:30', 'timezone' => 'Europe/Paris', 'inconnu' => 'ignoré'])[0]);
        [, $s] = $this->call('GET', '/api/settings', $t);
        self::assertSame([72, 'off', '07:30'], [$s['relanceHours'], $s['aiMode'], $s['recapMorning']]);
        self::assertArrayNotHasKey('inconnu', $s);
        foreach ([['relanceHours' => 5], ['aiMode' => 'skynet'], ['recapMorning' => '25:00'], ['dailyCap' => '5'], ['businessDays' => 'oui'], ['timezone' => 'Mars/Olympus']] as $bad) {
            self::assertSame(422, $this->call('PUT', '/api/settings', $t, $bad)[0], json_encode($bad));
        }
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame(48, $this->call('GET', '/api/settings', $other)[1]['relanceHours'], 'les réglages sont personnels');
    }
}
