<?php

namespace App\Tests;

use App\Tests\Support\ApiTestCase;

/** F-66 : règles visibles, modifiables, désactivables, propres à l'utilisateur. */
final class FollowRuleApiTest extends ApiTestCase
{
    public function testCrudValidationAndIsolation(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [$st, $r] = $this->call('POST', '/api/follow-rules', $t, ['kind' => 'domain', 'value' => '@Univ-Reims.fr']);
        self::assertSame(201, $st);
        self::assertSame('univ-reims.fr', $r['value'], 'normalisé : minuscules, sans arobase');
        [$st] = $this->call('POST', '/api/follow-rules', $t, ['kind' => 'domain', 'value' => 'univ-reims.fr']);
        self::assertSame(409, $st);
        [$st, $e] = $this->call('POST', '/api/follow-rules', $t, ['kind' => 'sender', 'value' => 'pas-une-adresse']);
        self::assertSame([422, 'value'], [$st, $e['field']]);
        [, $r2] = $this->call('PATCH', '/api/follow-rules/'.$r['id'], $t, ['enabled' => false]);
        self::assertFalse($r2['enabled']);
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/follow-rules', $other)[1]);
        self::assertSame(404, $this->call('DELETE', '/api/follow-rules/'.$r['id'], $other)[0]);
        self::assertSame(204, $this->call('DELETE', '/api/follow-rules/'.$r['id'], $t)[0]);
        self::assertSame([], $this->call('GET', '/api/follow-rules', $t)[1]);
    }
}
