<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\Suivi;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;

final class AuthAndIsolationTest extends ApiTestCase
{
    private function suivi(User $owner, string $subject): Suivi
    {
        $s = (new Suivi())->setSubject($subject)->setContact('X')->setOwner($owner);
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    public function testApiRequiresAToken(): void
    {
        self::assertSame(401, $this->call('GET', '/api/suivis')[0]);
        self::assertSame(401, $this->call('GET', '/api/suivis', 'jeton-bidon')[0]);
        self::assertSame(401, $this->call('GET', '/api/mails')[0]);
    }

    public function testDevLoginIssuesAWorkingTokenAndMeWorks(): void
    {
        [$st, $body] = $this->call('POST', '/api/auth/dev-login', null, ['username' => 'david']);
        self::assertSame(200, $st);
        [$st, $me] = $this->call('GET', '/api/me', $body['token']);
        self::assertSame([200, 'david'], [$st, $me['username']]);
        self::assertSame(422, $this->call('POST', '/api/auth/dev-login', null, ['username' => '../etc'])[0]);
    }

    public function testEachUserOnlySeesTheirOwnData(): void
    {
        $david = $this->user('david');
        $claire = $this->user('claire');
        $mine = $this->suivi($david, 'Le mien');
        $theirs = $this->suivi($claire, 'Le sien');
        $t = $this->tokenFor($david);

        [, $list] = $this->call('GET', '/api/suivis', $t);
        self::assertSame(['Le mien'], array_column($list, 'subject'));
        self::assertSame(200, $this->call('GET', '/api/suivis/'.$mine->getId(), $t)[0]);
        self::assertSame(404, $this->call('GET', '/api/suivis/'.$theirs->getId(), $t)[0], 'le suivi d’un autre est introuvable');
        self::assertSame(404, $this->call('POST', '/api/suivis/'.$theirs->getId().'/close', $t)[0]);
        self::assertSame(404, $this->call('PATCH', '/api/suivis/'.$theirs->getId(), $t, ['status' => 'done'], 'application/merge-patch+json')[0]);
        $this->em->clear();
        self::assertSame('new', $this->em->find(Suivi::class, $theirs->getId())->getStatus()->value);
    }

    public function testACreatedSuiviBelongsToItsAuthor(): void
    {
        $david = $this->user('david');
        [$st, $created] = $this->call('POST', '/api/suivis', $this->tokenFor($david), ['subject' => 'Neuf', 'contact' => 'Z', 'direction' => 'waiting', 'dueAt' => '2026-12-01']);
        self::assertSame(201, $st);
        $this->em->clear();
        self::assertSame($david->getId(), $this->em->find(Suivi::class, $created['id'])->getOwner()->getId());
    }

    public function testMailsAreIsolatedToo(): void
    {
        $david = $this->user('david');
        $claire = $this->user('claire');
        $acc = (new MailAccount())->setOwner($claire)->setLabel('c')->setAddress('c@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('c');
        $this->em->persist($acc);
        $m = Mail::fromHeader($acc, new \App\Mail\MailHeader('<a@x>', null, [], 'X', 'x@y.fr', [], 'Secret', new \DateTimeImmutable(), false, 1));
        $this->em->persist($m);
        $this->em->flush();
        self::assertSame([], $this->call('GET', '/api/mails', $this->tokenFor($david))[1]);
        self::assertSame(404, $this->call('GET', '/api/mails/'.$m->getId().'/body', $this->tokenFor($david))[0]);
        self::assertCount(1, $this->call('GET', '/api/mails', $this->tokenFor($claire))[1]);
    }

    public function testExpiredAndRevokedTokensAreRefused(): void
    {
        $u = $this->user('david');
        $t = $this->tokenFor($u);
        self::assertSame(200, $this->call('GET', '/api/me', $t)[0]);
        self::assertSame(204, $this->call('POST', '/api/auth/logout', $t)[0]);
        self::assertSame(401, $this->call('GET', '/api/me', $t)[0], 'session révoquée');

        $t2 = $this->tokenFor($u);
        $this->em->getConnection()->executeStatement('UPDATE api_token SET expires_at = :d', ['d' => '2000-01-01 00:00:00']);
        self::assertSame(401, $this->call('GET', '/api/me', $t2)[0], 'session expirée');
    }

    public function testCodeExchangeIsSingleUseAndReturnUrlIsAllowListed(): void
    {
        $u = $this->user('david');
        $cache = static::getContainer()->get(\Psr\Cache\CacheItemPoolInterface::class);
        $cache->save($cache->getItem('authcode_abc123')->set($u->getId())->expiresAfter(60));
        [$st, $r] = $this->call('POST', '/api/auth/token', null, ['code' => 'abc123']);
        self::assertSame(200, $st);
        self::assertNotEmpty($r['token']);
        self::assertSame(401, $this->call('POST', '/api/auth/token', null, ['code' => 'abc123'])[0], 'le code ne sert qu’une fois');
        self::assertSame(400, $this->call('GET', '/api/auth/cas/login?return=https://evil.example/steal')[0], 'pas de redirection ouverte');
    }
}
