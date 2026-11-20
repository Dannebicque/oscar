<?php

namespace App\Tests;

use App\Entity\MailAccount;
use App\Tests\Support\ApiTestCase;

final class MailAccountApiTest extends ApiTestCase
{
    private const PAYLOAD = ['label' => 'IUT', 'address' => 'david@iut-troyes.fr', 'imapHost' => 'imap.iut-troyes.fr', 'imapPort' => 993, 'imapEncryption' => 'ssl',
        'smtpHost' => 'smtp.iut-troyes.fr', 'smtpPort' => 587, 'smtpEncryption' => 'tls', 'username' => 'david', 'password' => 'secret-tres-prive'];

    public function testPasswordIsEncryptedAtRestAndNeverReturned(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [$st, $created] = $this->call('POST', '/api/mail-accounts', $t, self::PAYLOAD);
        self::assertSame(201, $st);
        self::assertStringNotContainsString('secret-tres-prive', json_encode($created));
        self::assertArrayNotHasKey('password', $created);
        self::assertArrayNotHasKey('passwordEncrypted', $created);

        $stored = $this->em->getConnection()->fetchOne('SELECT password_encrypted FROM mail_account');
        self::assertNotSame('', $stored);
        self::assertStringNotContainsString('secret-tres-prive', $stored);
        [, $got] = $this->call('GET', '/api/mail-accounts/'.$created['id'], $t);
        self::assertStringNotContainsString('secret', json_encode($got));
    }

    public function testAnotherUserCannotSeeOrTestMyAccount(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [, $created] = $this->call('POST', '/api/mail-accounts', $t, self::PAYLOAD);
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/mail-accounts', $other)[1]);
        self::assertSame(404, $this->call('GET', '/api/mail-accounts/'.$created['id'], $other)[0]);
        self::assertSame(404, $this->call('POST', '/api/mail-accounts/'.$created['id'].'/test', $other, [])[0]);
    }

    public function testValidationRejectsABadPortOrEncryption(): void
    {
        $t = $this->tokenFor($this->user('david'));
        self::assertSame(422, $this->call('POST', '/api/mail-accounts', $t, ['imapPort' => 99999] + self::PAYLOAD)[0]);
        self::assertSame(422, $this->call('POST', '/api/mail-accounts', $t, ['imapEncryption' => 'rot13'] + self::PAYLOAD)[0]);
    }

    public function testTestEndpointExplainsAFailureWithoutLeakingDetails(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [, $created] = $this->call('POST', '/api/mail-accounts', $t, ['imapHost' => '127.0.0.1', 'imapPort' => 1, 'imapEncryption' => 'none'] + self::PAYLOAD);
        [$st, $r] = $this->call('POST', '/api/mail-accounts/'.$created['id'].'/test', $t, []);
        self::assertSame(200, $st);
        self::assertFalse($r['ok']);
        self::assertStringContainsString('Vérifie', $r['message']);
        self::assertStringNotContainsString('secret', $r['message']);
    }
}
