<?php

namespace App\Controller;

use App\Entity\CalendarEvent;
use App\Entity\SharedMailbox;
use App\Entity\User;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Partage d'agenda avec l'équipe (F-26) : les créneaux OCCUPÉS des collègues qui l'ont accepté (réglage « Partager mon agenda pro »,
 * désactivé par défaut). Jamais un titre, un lieu ou un participant ; jamais l'agenda perso. Réservé aux membres de la même boîte partagée.
 */
final class TeamBusyController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/team/busy', methods: ['GET'])]
    public function __invoke(Request $r, #[CurrentUser] User $me): JsonResponse
    {
        $box = $this->em->getRepository(SharedMailbox::class)->findOneBy(['slug' => (string) $r->query->get('mailbox', '')]);
        if ($box === null || $box->memberOf($me) === null) {
            return new JsonResponse(['title' => 'Boîte partagée inconnue'], Response::HTTP_NOT_FOUND);
        }
        try {
            $from = new \DateTimeImmutable((string) $r->query->get('from', ''));
            $to = new \DateTimeImmutable((string) $r->query->get('to', ''));
        } catch (\Exception) {
            return new JsonResponse(['title' => 'Période invalide'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($to <= $from || $to > $from->modify('+62 days')) {
            return new JsonResponse(['title' => 'Période invalide (62 jours au plus)'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $out = [];
        foreach ($box->getMembers() as $member) {
            $u = $member->getUser();
            if ($u->getId() === $me->getId() || !((($this->em->getRepository(UserSettings::class)->findOneBy(['owner' => $u]) ?? new UserSettings($u))->all())['shareBusy'])) {
                continue;
            }
            $events = $this->em->createQueryBuilder()->select('e')->from(CalendarEvent::class, 'e')->join('e.calendar', 'c')
                ->where('e.owner = :u AND c.personal = false AND e.end > :from AND e.start < :to')
                ->setParameter('u', $u->getId())->setParameter('from', $from)->setParameter('to', $to)->orderBy('e.start', 'ASC')->setMaxResults(300)->getQuery()->getResult();
            foreach ($events as $e) {
                $out[] = ['member' => $u->getDisplayName(), 'start' => $e->getStart()->format(\DATE_ATOM), 'end' => $e->getEnd()->format(\DATE_ATOM)];
            }
        }

        return new JsonResponse($out);
    }
}
