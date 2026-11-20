<?php

namespace App\Recap;

use App\Entity\CalendarEvent;
use App\Entity\Suivi;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Contenu d'un récap (F-02) : mails importants non traités, événements à venir, tâches du jour et en retard,
 * relances dues. Déterministe : la date et les règles décident, pas l'IA. L'agenda perso n'y figure que sur réglage.
 */
final class RecapBuilder
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param array<string, scalar> $settings
     *
     * @return array{mails: list<array{subject: string, contact: string, reason: string}>, events: list<array{title: string, start: string, end: string}>, tasks: list<array{title: string, dueAt: ?string}>, relances: list<array{subject: string, contact: string}>}
     */
    public function build(User $user, \DateTimeImmutable $now, array $settings): array
    {
        $today = $now->format('Y-m-d');
        $cap = (int) ($settings['dailyCap'] ?? 5);
        $mails = $relances = [];
        foreach ($this->em->getRepository(Suivi::class)->findBy(['owner' => $user], ['dueAt' => 'ASC']) as $s) {
            if ($s->getStatus() === SuiviStatus::Done) {
                continue;
            }
            $due = $s->getDueAt()->format('Y-m-d');
            if ($s->isReplyReceived()) {
                $mails[] = ['subject' => $s->getSubject(), 'contact' => $s->getContact(), 'reason' => 'replyReceived'];
            } elseif ($due < $today) {
                $mails[] = ['subject' => $s->getSubject(), 'contact' => $s->getContact(), 'reason' => 'late'];
            } elseif ($s->getDirection() === SuiviDirection::Waiting && !$s->isNoRelance() && $s->getRelanceAt() !== null && $s->getRelanceAt()->format('Y-m-d') <= $today) {
                $relances[] = ['subject' => $s->getSubject(), 'contact' => $s->getContact()];
            }
        }
        $events = [];
        $qb = $this->em->createQueryBuilder()->select('e', 'c')->from(CalendarEvent::class, 'e')->join('e.calendar', 'c')
            ->where('e.owner = :u AND e.end > :now AND e.start < :end')->orderBy('e.start', 'ASC')
            ->setParameter('u', $user->getId())->setParameter('now', $now)->setParameter('end', $now->setTime(23, 59, 59));
        if (empty($settings['includePersonalCalendar'])) {
            $qb->andWhere('c.personal = false');
        }
        foreach ($qb->getQuery()->getResult() as $e) {
            $events[] = ['title' => $e->getTitle(), 'start' => $e->getStart()->format(\DATE_ATOM), 'end' => $e->getEnd()->format(\DATE_ATOM)];
        }
        $tasks = [];
        foreach ($this->em->getRepository(Task::class)->findBy(['owner' => $user, 'done' => false, 'toConfirm' => false], ['dueAt' => 'ASC']) as $t) {
            if ($t->getDueAt() !== null && $t->getDueAt()->format('Y-m-d') <= $today) {
                $tasks[] = ['title' => $t->getTitle(), 'dueAt' => $t->getDueAt()->format('Y-m-d')];
            }
        }

        // Le plafond limite les relances PROPOSÉES (F-63), pas l'information donnée à l'utilisateur sur les retards.
        return ['mails' => $mails, 'events' => $events, 'tasks' => $tasks, 'relances' => \array_slice($relances, 0, $cap)];
    }
}
