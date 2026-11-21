<?php

namespace App\Tests;

use App\Domain\FollowUpDelay;
use PHPUnit\Framework\TestCase;

/** F-12, F-54 : heures réelles ou jours ouvrés, au choix du compte. */
final class FollowUpDelayTest extends TestCase
{
    public function testRealHoursKeepWeekends(): void
    {
        // Vendredi 20 novembre 2026 + 48 h = dimanche.
        self::assertSame('2026-11-22', FollowUpDelay::dueDate(new \DateTimeImmutable('2026-11-20 10:00'), 48, false)->format('Y-m-d'));
    }

    public function testBusinessDaysSkipTheWeekend(): void
    {
        self::assertSame('2026-11-24', FollowUpDelay::dueDate(new \DateTimeImmutable('2026-11-20 10:00'), 48, true)->format('Y-m-d'), 'vendredi + 2 jours ouvrés = mardi');
        self::assertSame('2026-11-23', FollowUpDelay::dueDate(new \DateTimeImmutable('2026-11-20 10:00'), 24, true)->format('Y-m-d'), 'vendredi + 1 jour ouvré = lundi');
        self::assertSame('2026-11-24', FollowUpDelay::dueDate(new \DateTimeImmutable('2026-11-22 10:00'), 48, true)->format('Y-m-d'), 'depuis un dimanche : lundi et mardi');
        self::assertSame('2026-12-01', FollowUpDelay::dueDate(new \DateTimeImmutable('2026-11-20 10:00'), 168, true)->format('Y-m-d'), '7 jours ouvrés');
    }
}
