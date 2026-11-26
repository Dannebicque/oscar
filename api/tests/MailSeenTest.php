<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Mail\MailboxReaderFactory;
use App\Mail\MailHeader;
use App\Mail\MailSynchronizer;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\FakeReaderFactory;

/** F-16 / F-72 : lu et non lu suivent le serveur de messagerie. */
final class MailSeenTest extends ApiTestCase
{
    private Mail $mail;
    private MailAccount $account;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        FakeReaderFactory::$unseen = [];
        FakeReaderFactory::$seenCalls = [];
        FakeReaderFactory::$seenFails = false;
        FakeReaderFactory::$serverUids = [7];
        FakeReaderFactory::$body = 'Corps simulé';
        $david = $this->user('david');
        $this->account = (new MailAccount())->setOwner($david)->setLabel('IUT')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($this->account);
        $this->mail = Mail::fromHeader($this->account, new MailHeader('<m@x>', null, [], 'Sophie', 's@x.fr', [], 'Planning', new \DateTimeImmutable('2026-11-19 09:00:00'), false, 7, 'INBOX', false));
        $this->em->persist($this->mail);
        $this->em->flush();
        $this->token = $this->tokenFor($david);
    }

    public function testTheListSaysWhatIsUnreadAndMarkingGoesToTheServerFirst(): void
    {
        self::assertFalse($this->call('GET', '/api/mails', $this->token)[1][0]['seen']);

        [$st, $r] = $this->call('POST', '/api/mails/'.$this->mail->getId().'/seen', $this->token, ['seen' => true]);
        self::assertSame([200, true], [$st, $r['seen']]);
        self::assertSame([['INBOX', 7, true]], FakeReaderFactory::$seenCalls);
        self::assertTrue($this->call('GET', '/api/mails', $this->token)[1][0]['seen']);
    }

    public function testNothingChangesWhenTheServerFails(): void
    {
        FakeReaderFactory::$seenFails = true;
        self::assertSame(502, $this->call('POST', '/api/mails/'.$this->mail->getId().'/seen', $this->token, ['seen' => true])[0]);
        $this->em->clear();
        self::assertFalse($this->em->find(Mail::class, $this->mail->getId())->isSeen());
    }

    public function testBadInputAndSomeoneElsesMail(): void
    {
        self::assertSame(422, $this->call('POST', '/api/mails/'.$this->mail->getId().'/seen', $this->token, ['seen' => 'oui'])[0]);
        self::assertSame(404, $this->call('POST', '/api/mails/'.$this->mail->getId().'/seen', $this->tokenFor($this->user('claire')), ['seen' => true])[0]);
        self::assertSame([], FakeReaderFactory::$seenCalls);
    }

    public function testAMessageDeletedElsewhereIsSaidGoneThenLeaves(): void
    {
        FakeReaderFactory::$body = null;
        self::assertSame([200, []], [$this->call('GET', '/api/mails/'.$this->mail->getId().'/body', $this->token)[0], $this->call('GET', '/api/mails/'.$this->mail->getId().'/body', $this->token)[1]['paragraphs']], 'présent mais sans texte');
        FakeReaderFactory::$serverUids = [];
        self::assertSame(410, $this->call('GET', '/api/mails/'.$this->mail->getId().'/body', $this->token)[0]);

        $account = $this->em->find(MailAccount::class, $this->account->getId());
        $sync = static::getContainer()->get(MailSynchronizer::class);
        $reader = static::getContainer()->get(MailboxReaderFactory::class)->create($account);
        FakeReaderFactory::$serverUids = null; // le serveur ne répond pas : on ne retire rien
        $sync->sync($account, $reader);
        self::assertSame(1, $this->em->getRepository(Mail::class)->count([]));
        FakeReaderFactory::$serverUids = [];
        $sync->sync($account, $reader);
        self::assertSame(0, $this->em->getRepository(Mail::class)->count([]));
        FakeReaderFactory::$body = 'Corps simulé';
    }

    public function testSyncFollowsWhatWasReadElsewhere(): void
    {
        $sync = static::getContainer()->get(MailSynchronizer::class);
        $reader = static::getContainer()->get(MailboxReaderFactory::class)->create($this->account);

        $sync->sync($this->account, $reader); // le serveur n'annonce aucun non lu : lu depuis un autre logiciel
        self::assertTrue($this->mail->isSeen());

        FakeReaderFactory::$unseen = [7]; // remis en non lu ailleurs
        $sync->sync($this->account, $reader);
        self::assertFalse($this->mail->isSeen());
    }
}
