<?php

namespace App\Calendar;

use App\Entity\CalendarAccount;
use App\Entity\CalendarEvent;
use Doctrine\ORM\EntityManagerInterface;

/** Synchronise une fenêtre autour d'aujourd'hui : ajoute, met à jour, supprime ce que le serveur ne renvoie plus. */
final class CalendarSynchronizer
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly CalendarProvider $provider)
    {
    }

    /** @return array{added: int, updated: int, removed: int} */
    public function sync(CalendarAccount $account, ?\DateTimeImmutable $now = null, int $pastDays = 14, int $futureDays = 90): array
    {
        $now ??= new \DateTimeImmutable();
        $from = $now->modify("-$pastDays days")->setTime(0, 0);
        $to = $now->modify("+$futureDays days")->setTime(0, 0);
        $remote = [];
        foreach ($this->provider->events($account, $from, $to) as $e) {
            $remote[$e->uid] = $e;
        }
        $repo = $this->em->getRepository(CalendarEvent::class);
        $local = [];
        foreach ($repo->findBy(['calendar' => $account]) as $l) {
            $local[$l->getUid()] = $l;
        }
        $stats = ['added' => 0, 'updated' => 0, 'removed' => 0];
        foreach ($remote as $uid => $e) {
            $row = $local[$uid] ?? null;
            if ($row === null) {
                $row = (new CalendarEvent())->setOwner($account->getOwner())->setCalendar($account)->setUid($uid);
                $this->em->persist($row);
                ++$stats['added'];
            } elseif ($row->getTitle() !== $e->title || $row->getStart() != $e->start || $row->getEnd() != $e->end || $row->getLocation() !== $e->location || $row->getAttendees() !== $e->attendees) {
                ++$stats['updated'];
            }
            $row->setTitle($e->title)->setStart($e->start)->setEnd($e->end)->setLocation($e->location)->setAttendees($e->attendees);
        }
        $demo = str_starts_with($account->getUrl(), 'demo://');
        foreach ($local as $uid => $l) {
            // L'agenda de démonstration n'a pas de serveur pour garder ce qu'on y ajoute : on ne retire que ses propres événements.
            if ($demo && !str_starts_with((string) $uid, 'demo-')) {
                continue;
            }
            // On ne supprime que dans la fenêtre synchronisée : l'historique plus ancien reste consultable.
            if (!isset($remote[$uid]) && $l->getEnd() > $from && $l->getStart() < $to) {
                $this->em->remove($l);
                ++$stats['removed'];
            }
        }
        $account->markSynced();
        $this->em->flush();

        return $stats;
    }
}
