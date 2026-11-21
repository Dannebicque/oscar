<?php

namespace App\Tests\Mail;

use App\Entity\MailAccount;
use App\Entity\Suivi;
use App\Entity\User;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use App\Mail\MailboxReader;
use App\Mail\MailHeader;
use App\Mail\MailSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MailSynchronizerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MailSynchronizer $sync;
    private User $user;
    private MailAccount $account;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConfiguration()->setUseSchemaEditorApi(true);
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($this->em->getMetadataFactory()->getAllMetadata()); // repart d'une base vide, aussi sur PostgreSQL
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->sync = self::getContainer()->get(MailSynchronizer::class);
        $this->user = new User('david');
        $this->account = (new MailAccount())->setOwner($this->user)->setLabel('t')->setAddress('david@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('david');
        $this->em->persist($this->user);
        $this->em->persist($this->account);
        $this->em->flush();
    }

    private static function header(string $id, string $folder, int $uid, ?string $inReplyTo = null, bool $auto = false): MailHeader
    {
        return new MailHeader($id, $inReplyTo, [], 'Marc', 'marc@x.fr', ['david@iut-troyes.fr'], 'Devis', new \DateTimeImmutable('2026-11-20 10:00'), $auto, $uid, $folder);
    }

    private function suivi(string $mailId, SuiviStatus $status = SuiviStatus::Waiting): Suivi
    {
        $s = (new Suivi())->setSubject('Devis')->setContact('Marc')->setDirection(SuiviDirection::Waiting)->setStatus($status)->setMailId($mailId)->setOwner($this->user);
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    /** @param list<MailHeader> $all */
    private function reader(array $all): MailboxReader
    {
        return new class($all) implements MailboxReader {
            public function __construct(private array $all) {}
            public function attachments(string $folder, int $uid): array { return []; }
            public function attachment(string $folder, int $uid, int $index): ?array { return null; }

            public function fetchAfter(string $folder, int $afterUid): array
            {
                return array_values(array_filter($this->all, static fn (MailHeader $h) => $h->folder === $folder && $h->uid > $afterUid));
            }

            public function fetchBody(string $folder, int $uid): ?string
            {
                return null;
            }
        };
    }

    public function testSyncIsIncrementalByUid(): void
    {
        $reader = $this->reader([self::header('<a>', 'INBOX', 1), self::header('<b>', 'INBOX', 2)]);
        self::assertSame(2, $this->sync->sync($this->account, $reader)['fetched']);
        self::assertSame(0, $this->sync->sync($this->account, $reader)['fetched'], 'rien de nouveau : pas de doublon');
        $reader2 = $this->reader([self::header('<a>', 'INBOX', 1), self::header('<b>', 'INBOX', 2), self::header('<c>', 'INBOX', 3)]);
        self::assertSame(1, $this->sync->sync($this->account, $reader2)['fetched']);
    }

    public function testRealReplyFlagsTheSuiviButNeverClosesIt(): void
    {
        $s = $this->suivi('<sent-1>');
        $r = $this->sync->sync($this->account, $this->reader([self::header('<r>', 'INBOX', 1, '<sent-1>')]));
        self::assertSame(1, $r['replies']);
        $this->em->refresh($s);
        self::assertTrue($s->isReplyReceived());
        self::assertSame(SuiviStatus::Waiting, $s->getStatus(), 'jamais de clôture automatique (F-65)');
    }

    public function testAutoReplyAndOtherThreadsDoNotCount(): void
    {
        $s = $this->suivi('<sent-1>');
        $r = $this->sync->sync($this->account, $this->reader([self::header('<auto>', 'INBOX', 1, '<sent-1>', true), self::header('<other>', 'INBOX', 2, '<ailleurs>')]));
        self::assertSame(0, $r['replies']);
        $this->em->refresh($s);
        self::assertFalse($s->isReplyReceived());
    }

    public function testClosedSuiviIsLeftAlone(): void
    {
        $s = $this->suivi('<sent-1>', SuiviStatus::Done);
        self::assertSame(0, $this->sync->sync($this->account, $this->reader([self::header('<r>', 'INBOX', 1, '<sent-1>')]))['replies']);
        $this->em->refresh($s);
        self::assertFalse($s->isReplyReceived());
    }

    public function testSentFolderIsNotTreatedAsAReply(): void
    {
        $this->suivi('<sent-1>');
        self::assertSame(0, $this->sync->sync($this->account, $this->reader([self::header('<mine>', 'Sent', 1, '<sent-1>')]))['replies']);
    }
}
