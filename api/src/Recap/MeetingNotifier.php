<?php

namespace App\Recap;

use App\Entity\CalendarEvent;
use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Notification\NotificationCenter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prévient quelques minutes avant un rendez-vous (F-71) et rappelle combien d'échanges de mails récents existent avec les participants.
 * Calcul local : l'IA ne reçoit ni titre ni participant. Un rappel par événement, jamais deux (clé de déduplication).
 */
final class MeetingNotifier
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly NotificationCenter $center)
    {
    }

    public function notify(User $user, \DateTimeImmutable $now): int
    {
        $settings = ($this->em->getRepository(UserSettings::class)->findOneBy(['owner' => $user]) ?? new UserSettings($user))->all();
        $lead = (int) $settings['meetingLeadMinutes'];
        if ($lead <= 0) {
            return 0;
        }
        $qb = $this->em->createQueryBuilder()->select('e', 'c')->from(CalendarEvent::class, 'e')->join('e.calendar', 'c')
            ->where('e.owner = :u AND e.start > :now AND e.start <= :soon')->orderBy('e.start', 'ASC')
            ->setParameter('u', $user->getId())->setParameter('now', $now)->setParameter('soon', $now->modify('+'.$lead.' minutes'));
        if (empty($settings['includePersonalCalendar'])) {
            $qb->andWhere('c.personal = false');
        }
        $mine = array_map(static fn (MailAccount $a) => strtolower($a->getAddress()), $this->em->getRepository(MailAccount::class)->findBy(['owner' => $user]));
        $sent = 0;
        foreach ($qb->getQuery()->getResult() as $e) {
            /** @var CalendarEvent $e */
            $people = array_values(array_filter($e->getAttendees(), static fn (array $p) => !\in_array(strtolower($p['address']), $mine, true)));
            $n = $this->exchanges($user, array_map(static fn (array $p) => strtolower($p['address']), $people), $now);
            $minutes = max(1, (int) ceil(($e->getStart()->getTimestamp() - $now->getTimestamp()) / 60));
            $params = ['title' => $e->getTitle(), 'minutes' => $minutes, 'who' => $people[0]['name'] ?? '', 'n' => $n];
            if ($this->center->deliver($user, new Notification('meetingSoon', $params, 'agenda', 'meeting:'.$e->getId().':'.$e->getStart()->getTimestamp(), $now))) {
                ++$sent;
            }
        }
        $this->em->flush();

        return $sent;
    }

    /** @param list<string> $addresses */
    private function exchanges(User $user, array $addresses, \DateTimeImmutable $now): int
    {
        if ($addresses === []) {
            return 0;
        }
        $n = 0;
        foreach ($this->em->getRepository(Mail::class)->findBy(['owner' => $user], ['date' => 'DESC'], 300) as $m) {
            /** @var Mail $m */
            if ($m->getDate() < $now->modify('-90 days')) {
                break;
            }
            if (!\in_array($m->getFolder(), ['INBOX', 'Sent', 'Archive'], true)) {
                continue;
            }
            $to = array_map('strtolower', $m->toHeader()->to);
            if (\in_array(strtolower($m->getFromAddress()), $addresses, true) || array_intersect($to, $addresses) !== []) {
                ++$n;
            }
        }

        return $n;
    }
}
