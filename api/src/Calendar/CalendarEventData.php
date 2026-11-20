<?php

namespace App\Calendar;

/** Événement lu ou à écrire sur un serveur CalDAV. */
final readonly class CalendarEventData
{
    public function __construct(
        public string $uid,
        public string $title,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public ?string $location = null,
    ) {
    }
}
