<?php

namespace App\Tests\Support;

use App\Calendar\CalendarEventData;
use App\Calendar\CalendarProvider;
use App\Entity\CalendarAccount;

final class FakeCalendarProvider implements CalendarProvider
{
    /** @var list<CalendarEventData> */
    public array $remote = [];
    /** @var list<CalendarEventData> */
    public array $created = [];
    public bool $fail = false;

    public function events(CalendarAccount $account, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($this->fail) {
            throw new \RuntimeException('connection refused');
        }

        return $this->remote;
    }

    public function create(CalendarAccount $account, CalendarEventData $event): void
    {
        if ($this->fail) {
            throw new \RuntimeException('serveur down');
        }
        $this->created[] = $event;
    }
}
