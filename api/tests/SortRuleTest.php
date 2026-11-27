<?php

namespace App\Tests;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\SortRule;
use App\Mail\MailHeader;
use App\Mail\MailSynchronizer;
use App\Mail\MailboxMover;
use App\Mail\MailboxReader;
use App\Tests\Support\ApiTestCase;

/** F-15 : règles de tri déterministes. Un mail qui a un suivi n'est jamais rangé ; jamais de suppression définitive. */
final class SortRuleTest extends ApiTestCase
{
    public function testCrudValidationAndIsolation(): void
    {
        $t = $this->tokenFor($this->user('david'));
        [$st, $r] = $this->call('POST', '/api/sort-rules', $t, ['field' => 'domain', 'value' => '@Newsletter.fr', 'action' => 'archive']);
        self::assertSame(201, $st);
        self::assertSame('newsletter.fr', $r['value']);
        self::assertSame(409, $this->call('POST', '/api/sort-rules', $t, ['field' => 'domain', 'value' => 'newsletter.fr', 'action' => 'trash'])[0]);
        self::assertSame(422, $this->call('POST', '/api/sort-rules', $t, ['field' => 'subject', 'value' => 'ab', 'action' => 'trash'])[0]);
        self::assertSame(422, $this->call('POST', '/api/sort-rules', $t, ['field' => 'subject', 'value' => 'promo', 'action' => 'delete'])[0], 'pas de suppression définitive');
        $other = $this->tokenFor($this->user('claire'));
        self::assertSame([], $this->call('GET', '/api/sort-rules', $other)[1]);
        self::assertSame(404, $this->call('DELETE', '/api/sort-rules/'.$r['id'], $other)[0]);
        self::assertSame(204, $this->call('DELETE', '/api/sort-rules/'.$r['id'], $t)[0]);
    }

    public function testSynchronisationMovesMatchingMailAndLeavesTheRestAlone(): void
    {
        $david = $this->user('david');
        $acc = (new MailAccount())->setOwner($david)->setLabel('t')->setAddress('d@x.fr')->setImapHost('h')->setSmtpHost('h')->setUsername('d');
        $this->em->persist($acc);
        $this->em->persist(new SortRule($david, 'domain', 'promo.fr', 'archive'));
        $this->em->persist(new SortRule($david, 'subject', 'facture', 'trash'));
        $this->em->flush();
        $h = static fn (string $id, string $from, string $subject, int $uid) => new MailHeader($id, null, [], 'X', $from, ['d@x.fr'], $subject, new \DateTimeImmutable('2026-11-20 10:00'), false, $uid, 'INBOX');
        $reader = new class([$h('<a@p>', 'x@promo.fr', 'Offre', 1), $h('<b@p>', 'x@ok.fr', 'Votre facture', 2), $h('<c@p>', 'x@ok.fr', 'Bonjour', 3)]) implements MailboxReader, MailboxMover {
            public array $moves = [];
            public function __construct(private array $all) {}
            public function fetchAfter(string $folder, int $afterUid): array { return $folder === 'INBOX' ? array_values(array_filter($this->all, fn ($x) => $x->uid > $afterUid)) : []; }
            public function fetchContent(string $folder, int $uid): ?\App\Mail\MailContent { return null; }
            public function fetchBody(string $folder, int $uid): ?string { return null; }
            public function attachments(string $folder, int $uid): array { return []; }
            public function attachment(string $folder, int $uid, int $index): ?array { return null; }
            public function invitation(string $folder, int $uid): ?string { return null; }
            public function move(string $folder, int $uid, string $messageId, string $target): ?int { $this->moves[] = [$uid, $target]; return 900 + $uid; }
        };
        static::getContainer()->get(MailSynchronizer::class)->sync($acc, $reader);
        self::assertSame([[1, 'Archive'], [2, 'Trash']], $reader->moves);
        $byId = fn (string $id) => $this->em->getRepository(Mail::class)->findOneBy(['messageId' => $id]);
        self::assertSame(['Archive', 901], [$byId('<a@p>')->getFolder(), $byId('<a@p>')->getUid()]);
        self::assertSame('Trash', $byId('<b@p>')->getFolder());
        self::assertSame('INBOX', $byId('<c@p>')->getFolder());
    }
}
