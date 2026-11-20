<?php

namespace App\Tests\Mail;

use App\Mail\DemandGrouper;
use App\Mail\MailHeader;
use App\Mail\ReplyDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReplyDetectorTest extends TestCase
{
    private static function mail(string $id, ?string $inReplyTo = null, array $refs = [], bool $auto = false, string $date = '2026-11-20 10:00'): MailHeader
    {
        return new MailHeader($id, $inReplyTo, $refs, 'X', 'x@y.fr', ['david@iut-troyes.fr'], 's', new \DateTimeImmutable($date), $auto);
    }

    public function testReplyIsDetectedByInReplyToNotBySubject(): void
    {
        $d = new ReplyDetector();
        self::assertTrue($d->isReply(self::mail('<r1>', '<sent-1>'), '<sent-1>'));
        self::assertFalse($d->isReply(self::mail('<r2>'), '<sent-1>'), 'même objet mais pas dans le fil');
    }

    public function testReplyIsDetectedByReferencesAndIgnoresAngleBracketsAndCase(): void
    {
        self::assertTrue((new ReplyDetector())->isReply(self::mail('<r>', null, ['<A>', '<Sent-1@X>']), 'sent-1@x'));
    }

    public function testAutomaticReplyDoesNotCount(): void
    {
        self::assertFalse((new ReplyDetector())->isReply(self::mail('<r>', '<sent-1>', [], true), '<sent-1>'));
    }

    public function testAnyRecipientCanReplyAndTheEarliestIsKept(): void
    {
        $first = (new ReplyDetector())->firstReply(
            [self::mail('<late>', '<s>', [], false, '2026-11-21 09:00'), self::mail('<early>', '<s>', [], false, '2026-11-20 12:00'), self::mail('<auto>', '<s>', [], true, '2026-11-20 08:00')],
            '<s>',
        );
        self::assertSame('<early>', $first?->messageId);
    }

    #[DataProvider('automaticHeaders')]
    public function testAutomaticHeaders(?string $auto, ?string $precedence, ?string $suppress, bool $expected): void
    {
        self::assertSame($expected, ReplyDetector::looksAutomatic($auto, $precedence, $suppress));
    }

    public static function automaticHeaders(): iterable
    {
        yield 'rien' => [null, null, null, false];
        yield 'Auto-Submitted: no' => ['no', null, null, false];
        yield 'auto-replied' => ['auto-replied', null, null, true];
        yield 'Precedence: bulk' => [null, 'bulk', null, true];
        yield 'X-Auto-Response-Suppress' => [null, null, 'All', true];
    }

    public function testSameMessageInSeveralMailboxesIsOneDemand(): void
    {
        $groups = (new DemandGrouper())->group([self::mail('<Alias-1@x>'), self::mail('<alias-1@x>'), self::mail('alias-1@x'), self::mail('<autre@x>')]);
        self::assertCount(2, $groups);
        self::assertCount(3, $groups['alias-1@x']);
    }
}
