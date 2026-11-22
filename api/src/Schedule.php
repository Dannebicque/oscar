<?php

namespace App;

use App\Mail\SendDueMailsMessage;
use App\Recap\CheckMeetingsMessage;
use App\Recap\CheckRecapsMessage;
use App\Recap\CheckRelancesMessage;
use App\Recap\SyncAllMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            // Récaps du matin et du soir : vérifiés chaque minute, envoyés une seule fois par créneau et par jour (N-08).
            // Synchronisation incrémentale des boîtes et agendas (F-10, F-20).
            ->add(RecurringMessage::every('2 minutes', new SyncAllMessage()))
            ->add(RecurringMessage::every('1 minute', new CheckRecapsMessage()))
            // Mails programmés : envoyés à l'heure choisie par l'utilisateur (F-73).
            ->add(RecurringMessage::every('1 minute', new SendDueMailsMessage()))
            // Rendez-vous imminents : un rappel par événement, quelques minutes avant (durée réglable).
            ->add(RecurringMessage::every('1 minute', new CheckMeetingsMessage()))
            ->add(RecurringMessage::every('15 minutes', new CheckRelancesMessage()))
        ;
    }
}
