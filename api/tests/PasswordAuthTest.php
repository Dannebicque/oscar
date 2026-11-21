<?php

namespace App\Tests;

use App\Entity\User;
use App\Tests\Support\ApiTestCase;

/** Comptes locaux : inscription, connexion, mot de passe oublié. Aucune fuite sur l'existence d'un compte. */
final class PasswordAuthTest extends ApiTestCase
{
    private const PASSWORD = 'un-mot-de-passe-solide';

    private function register(string $email = 'david@iut-troyes.fr'): array
    {
        return $this->call('POST', '/api/auth/register', null, ['name' => 'David', 'email' => $email, 'password' => self::PASSWORD]);
    }

    public function testRegisterThenLoginGivesATokenAndTheHashIsNeverThePassword(): void
    {
        [$st, $r] = $this->register();
        self::assertSame(201, $st);
        [$st, $me] = $this->call('GET', '/api/me', $r['token']);
        self::assertSame('david@iut-troyes.fr', $me['email']);
        $u = $this->em->getRepository(User::class)->findOneBy(['email' => 'david@iut-troyes.fr']);
        self::assertNotSame(self::PASSWORD, $u->getPassword());
        [$st, $l] = $this->call('POST', '/api/auth/login', null, ['email' => 'DAVID@iut-troyes.fr', 'password' => self::PASSWORD]);
        self::assertSame(200, $st, 'l’adresse est insensible à la casse');
        self::assertNotEmpty($l['token']);
    }

    public function testWeakPasswordsAndDuplicatesAreRefusedWithAClearMessage(): void
    {
        [$st, $r] = $this->call('POST', '/api/auth/register', null, ['name' => 'D', 'email' => 'd@x.fr', 'password' => 'court']);
        self::assertSame(422, $st);
        self::assertSame('password', $r['field']);
        $this->register();
        [$st, $r] = $this->register();
        self::assertSame(409, $st);
        self::assertStringContainsString('déjà un compte', $r['title']);
    }

    public function testLoginMessageIsTheSameForAnUnknownAccountAndAWrongPasswordThenThrottles(): void
    {
        $this->register();
        [$a, $ra] = $this->call('POST', '/api/auth/login', null, ['email' => 'david@iut-troyes.fr', 'password' => 'faux-faux-faux']);
        [$b, $rb] = $this->call('POST', '/api/auth/login', null, ['email' => 'inconnu@x.fr', 'password' => 'faux-faux-faux']);
        self::assertSame([401, 401], [$a, $b]);
        self::assertSame($ra['title'], $rb['title'], 'on ne révèle pas quelles adresses ont un compte');
        for ($i = 0; $i < 4; ++$i) {
            $this->call('POST', '/api/auth/login', null, ['email' => 'david@iut-troyes.fr', 'password' => 'faux-faux-faux']);
        }
        [$st] = $this->call('POST', '/api/auth/login', null, ['email' => 'david@iut-troyes.fr', 'password' => self::PASSWORD]);
        self::assertSame(429, $st, 'cinq échecs : même le bon mot de passe attend');
    }

    public function testForgotThenResetIsSingleUseLogsOutEverywhereAndNeverRevealsTheAccount(): void
    {
        [, $r] = $this->register();
        [$st] = $this->call('POST', '/api/auth/forgot', null, ['email' => 'inconnu@x.fr']);
        self::assertSame(204, $st, 'même réponse pour une adresse sans compte');
        [$st, $f] = $this->call('POST', '/api/auth/forgot', null, ['email' => 'david@iut-troyes.fr']);
        self::assertSame(200, $st);
        preg_match('/token=([a-f0-9]+)/', $f['devLink'], $m);
        [$st] = $this->call('POST', '/api/auth/reset', null, ['token' => $m[1], 'password' => 'court']);
        self::assertSame(422, $st);
        [$st] = $this->call('POST', '/api/auth/reset', null, ['token' => $m[1], 'password' => 'nouveau-mot-de-passe-1']);
        self::assertSame(204, $st);
        [$st] = $this->call('POST', '/api/auth/reset', null, ['token' => $m[1], 'password' => 'encore-un-autre-mdp-2']);
        self::assertSame(422, $st, 'le lien ne sert qu’une fois');
        [$st] = $this->call('GET', '/api/me', $r['token']);
        self::assertSame(401, $st, 'les anciennes sessions sont fermées');
        [$st] = $this->call('POST', '/api/auth/login', null, ['email' => 'david@iut-troyes.fr', 'password' => 'nouveau-mot-de-passe-1']);
        self::assertSame(200, $st);
    }

    public function testConfigAndProbeGuards(): void
    {
        [$st, $c] = $this->call('GET', '/api/auth/config');
        self::assertSame(200, $st);
        self::assertTrue($c['password'] && $c['registration'] && $c['dev']);
        [$st] = $this->call('POST', '/api/mail-accounts/probe', null, ['imapHost' => 'x']);
        self::assertSame(401, $st, 'le test de serveurs exige d’être connecté');
        [, $r] = $this->register();
        [$st] = $this->call('POST', '/api/mail-accounts/probe', $r['token'], ['imapHost' => 'x']);
        self::assertSame(422, $st);
    }

    public function testChangePasswordChecksTheCurrentOneAndSignsOutOtherDevices(): void
    {
        [, $a] = $this->register();
        [, $b] = $this->call('POST', '/api/auth/login', null, ['email' => 'david@iut-troyes.fr', 'password' => self::PASSWORD]);
        [$st] = $this->call('POST', '/api/me/password', $a['token'], ['current' => 'mauvais-mot-de-passe', 'password' => 'un-autre-mot-de-passe']);
        self::assertSame(422, $st);
        [$st] = $this->call('POST', '/api/me/password', $a['token'], ['current' => self::PASSWORD, 'password' => 'un-autre-mot-de-passe']);
        self::assertSame(204, $st);
        [$st] = $this->call('GET', '/api/me', $a['token']);
        self::assertSame(200, $st, 'la session qui a changé le mot de passe reste ouverte');
        [$st] = $this->call('GET', '/api/me', $b['token']);
        self::assertSame(401, $st, 'l’autre appareil est déconnecté');
    }

    public function testDeleteAccountNeedsThePasswordThenRemovesEverything(): void
    {
        [, $a] = $this->register();
        [$st] = $this->call('DELETE', '/api/me', $a['token'], ['password' => 'faux']);
        self::assertSame(422, $st);
        self::assertNotNull($this->em->getRepository(User::class)->findOneBy(['email' => 'david@iut-troyes.fr']));
        [$st] = $this->call('DELETE', '/api/me', $a['token'], ['password' => self::PASSWORD]);
        self::assertSame(204, $st);
        $this->em->clear();
        self::assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'david@iut-troyes.fr']));
        [$st] = $this->call('GET', '/api/me', $a['token']);
        self::assertSame(401, $st);
    }
}
