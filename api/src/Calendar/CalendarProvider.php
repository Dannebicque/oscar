<?php

namespace App\Calendar;

use App\Entity\CalendarAccount;

/** Interface commune des agendas (CalDAV aujourd'hui, Google Agenda en version 2). */
interface CalendarProvider
{
    /** @return list<CalendarEventData> événements chevauchant [$from, $to[ (récurrences développées) */
    public function events(CalendarAccount $account, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** Écrit un événement sur le serveur. @throws \Throwable si le serveur refuse */
    public function create(CalendarAccount $account, CalendarEventData $event): void;
}
