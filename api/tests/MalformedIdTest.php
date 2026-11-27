<?php

namespace App\Tests;

use App\Tests\Support\ApiTestCase;

/** F-79 : un identifiant mal formé est « inconnu », jamais une erreur 500. Ne mord que sur PostgreSQL (CI) : SQLite accepte tout texte. */
final class MalformedIdTest extends ApiTestCase
{
    public function testBadIdentifiersAreUnknownEverywhere(): void
    {
        $t = $this->tokenFor($this->user('david'));
        foreach ([['GET', 'mails/abc/body'], ['GET', 'mails/abc/hints'], ['GET', 'suivis/abc'], ['DELETE', 'scheduled-mails/abc'], ['DELETE', 'identities/abc'], ['POST', 'mail-accounts/abc/test']] as [$method, $path]) {
            self::assertSame(404, $this->call($method, '/api/'.$path, $t)[0], "$method $path");
        }
    }
}
