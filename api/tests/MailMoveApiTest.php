<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Mail\MailHeader;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeReaderFactory;

/** F-16 : archiver, corbeille, retour en boîte de réception. Serveur d'abord, base ensuite, isolation par utilisateur. */
final class MailMoveApiTest extends ApiTestCase
{
    private Mail $mail;
    private string $token;
    private string $other;

    protected function setUp(): void
    {
        parent::setUp();
        FakeReaderFactory::$moved = [];
        FakeReaderFactory::$moveFails = false;
        $david = $this->user('david');
        $account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($account);
        $this->mail = Mail::fromHeader($account, new MailHeader('<m@x>', null, [], 'Sophie', 's@x.fr', [], 'Planning', new \DateTimeImmutable('2026-11-19 09:00:00'), false, 7, 'INBOX'));
        $this->em->persist($this->mail);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
        $this->other = $this->tokenFor($this->user('claire'));
    }

    public function testArchiveMovesOnTheServerThenUpdatesTheRow(): void
    {
        [$st, $r] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Archive']);
        self::assertSame(200, $st);
        self::assertSame('Archive', $r['folder']);
        self::assertSame([['INBOX', 7, 'Archive']], FakeReaderFactory::$moved);
        $this->em->clear();
        $m = $this->em->find(Mail::class, $this->mail->getId());
        self::assertSame('Archive', $m->getFolder());
        self::assertSame(507, $m->getUid(), 'le message a un nouvel UID dans le dossier d’arrivée');
    }

    public function testNothingChangesWhenTheServerFailsAndTheMessageIsNeverDeleted(): void
    {
        FakeReaderFactory::$moveFails = true;
        [$st] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Trash']);
        self::assertSame(502, $st);
        $this->em->clear();
        self::assertSame('INBOX', $this->em->find(Mail::class, $this->mail->getId())->getFolder());
        [$st] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->token, ['folder' => 'Nulle part']);
        self::assertSame(422, $st);
    }

    public function testSomeoneElsesMailIsUnknown(): void
    {
        [$st] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/move', $this->other, ['folder' => 'Trash']);
        self::assertSame(404, $st);
        self::assertSame([], FakeReaderFactory::$moved);
    }
}
