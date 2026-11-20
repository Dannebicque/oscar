<?php

namespace App\Controller;

use App\Calendar\CalendarEventData;
use App\Calendar\CalendarProvider;
use App\Entity\CalendarAccount;
use App\Entity\CalendarEvent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Création d'un événement : écrit d'abord sur le serveur CalDAV, ne garde rien en local si le serveur refuse. */
final class CalendarEventController extends AbstractController
{
    #[Route('/api/calendar-events', methods: ['POST'])]
    public function create(Request $r, #[CurrentUser] User $user, EntityManagerInterface $em, CalendarProvider $provider, ValidatorInterface $validator): JsonResponse
    {
        $in = $r->toArray();
        $account = $em->find(CalendarAccount::class, (string) ($in['calendarId'] ?? ''));
        if ($account === null || $account->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['title' => 'Agenda inconnu.'], Response::HTTP_NOT_FOUND);
        }
        try {
            $event = (new CalendarEvent())->setOwner($user)->setCalendar($account)->setTitle(trim((string) ($in['title'] ?? '')))
                ->setStart(new \DateTimeImmutable((string) ($in['start'] ?? '')))->setEnd(new \DateTimeImmutable((string) ($in['end'] ?? '')))
                ->setLocation(isset($in['location']) && trim((string) $in['location']) !== '' ? trim((string) $in['location']) : null);
        } catch (\Exception) {
            return new JsonResponse(['title' => 'Dates invalides.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (\count($validator->validate($event)) > 0) {
            return new JsonResponse(['title' => 'Titre requis et fin après le début.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $provider->create($account, new CalendarEventData($event->getUid(), $event->getTitle(), $event->getStart(), $event->getEnd(), $event->getLocation()));
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Le serveur d’agenda a refusé l’événement. Rien n’a été ajouté, réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }
        $em->persist($event);
        $em->flush();

        return new JsonResponse(['id' => $event->getId(), 'calendarId' => $account->getId(), 'title' => $event->getTitle(), 'start' => $event->getStart()->format(\DATE_ATOM), 'end' => $event->getEnd()->format(\DATE_ATOM), 'location' => $event->getLocation()], Response::HTTP_CREATED);
    }
}
