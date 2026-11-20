<?php

namespace App\Recap;

use App\Entity\Notification;
use App\Entity\Suivi;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prévient quand des relances sont dues (F-75, F-63). Elle ne relance jamais : elle signale.
 * Une seule alerte par fait et par jour ; plusieurs relances sont regroupées en une entrée « N relances à faire ».
 */
final class RelanceNotifier
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function notify(User $user, \DateTimeImmutable $now): int
    {
        $settings = ($this->em->getRepository(UserSettings::class)->findOneBy(['owner' => $user]) ?? new UserSettings($user))->all();
        $day = $now->format('Y-m-d');
        $due = array_values(array_filter(
            $this->em->getRepository(Suivi::class)->findBy(['owner' => $user, 'direction' => SuiviDirection::Waiting, 'noRelance' => false, 'replyReceived' => false], ['relanceAt' => 'ASC']),
            static fn (Suivi $s) => $s->getStatus() !== SuiviStatus::Done && $s->getRelanceAt() !== null && $s->getRelanceAt()->format('Y-m-d') <= $day,
        ));
        $due = \array_slice($due, 0, (int) $settings['dailyCap']); // plafond quotidien des relances proposées
        if ($due === []) {
            return 0;
        }
        $n = $due === [] ? 0 : (\count($due) === 1
            ? $this->once($user, new Notification('relanceDue', ['subject' => $due[0]->getSubject(), 'who' => $due[0]->getContact(), 'n' => 1], 'suivis', 'relance:'.$due[0]->getId().':'.$day, $now))
            : $this->once($user, new Notification('relanceDue', ['n' => \count($due)], 'suivis', 'relance:group:'.$day.':'.\count($due), $now)));
        $this->em->flush();

        return $n;
    }

    private function once(User $user, Notification $n): int
    {
        if ($this->em->getRepository(Notification::class)->findOneBy(['owner' => $user, 'dedupeKey' => $n->getDedupeKey()]) !== null) {
            return 0;
        }
        $this->em->persist($n->setOwner($user));

        return 1;
    }
}
