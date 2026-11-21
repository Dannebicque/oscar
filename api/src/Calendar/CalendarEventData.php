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
        /** @var list<array{name: string, address: string}> participants (ORGANIZER et ATTENDEE), pour relier l'événement aux échanges de mails */
        public array $attendees = [],
    ) {
    }
}
