<?php

namespace App\Tests;

use App\Entity\MailAccount;
use App\Tests\Support\ApiTestCase;

/** F-19 : signature par compte, modèles de message. */
final class TemplatesTest extends ApiTestCase
{
    public function testTemplatesCrudValidationAndIsolation(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [$st, $r] = $this->call('POST', '/api/templates', $t, ['name' => 'Accusé de réception', 'body' => 'Bonjour {prenom}, bien reçu.']);
        self::assertSame(201, $st);
        [$st, $e] = $this->call('POST', '/api/templates', $t, ['name' => '', 'body' => 'x']);
        self::assertSame([422, 'name'], [$st, $e['field']]);
        [$st, $e] = $this->call('POST', '/api/templates', $t, ['name' => 'Vide', 'body' => '   ']);
        self::assertSame([422, 'body'], [$st, $e['field']]);
        [, $u] = $this->call('PUT', '/api/templates/'.$r['id'], $t, ['name' => 'Accusé', 'body' => 'Bien reçu.']);
        self::assertSame('Accusé', $u['name']);
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/templates', $other)[1]);
        self::assertSame(404, $this->call('DELETE', '/api/templates/'.$r['id'], $other)[0]);
        self::assertSame(204, $this->call('DELETE', '/api/templates/'.$r['id'], $t)[0]);
    }

    public function testAccountSignatureIsStoredPerAccountAndBlankMeansDefault(): void
    {
        $u = $this->user('david');
        $a = (new MailAccount())->setOwner($u)->setLabel('IUT')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($a);
        $this->em->flush();
        $t = $this->tokenFor($u);
        [$st, $r] = $this->call('PATCH', '/api/mail-accounts/'.$a->getId(), $t, ['signature' => "David\nIUT de Troyes"], 'application/merge-patch+json');
        self::assertSame(200, $st);
        self::assertSame("David\nIUT de Troyes", $r['signature']);
        [, $r] = $this->call('PATCH', '/api/mail-accounts/'.$a->getId(), $t, ['signature' => '  '], 'application/merge-patch+json');
        self::assertNull($r['signature']);
    }
}
