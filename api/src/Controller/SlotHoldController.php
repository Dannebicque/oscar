<?php

namespace App\Controller;

use App\Entity\SlotHold;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

/**
 * Créneaux en provisoire (F-74) : les voir et les libérer, chacun les siens. En retenir un = créer l'événement (point d'accès de l'agenda)
 * puis libérer le groupe. Rien n'est écrit sur le serveur d'agenda ici.
 */
final class SlotHoldController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/slot-holds', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        // Un créneau passé disparaît de lui-même : il ne réserve plus rien.
        $this->em->createQuery('DELETE FROM '.SlotHold::class.' h WHERE h.owner = :u AND h.end <= :now')
            ->setParameter('u', $user->getId())->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')), \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE)->execute();
        $rows = $this->em->getRepository(SlotHold::class)->findBy(['owner' => $user], ['start' => 'ASC', 'id' => 'ASC']);

        return new JsonResponse(array_map(static fn (SlotHold $h) => $h->toArray(), $rows));
    }

    #[Route('/api/slot-holds/{id}', methods: ['DELETE'])]
    public function release(string $id, #[CurrentUser] User $user): JsonResponse
    {
        return $this->remove(Uuid::isValid($id) ? $this->em->getRepository(SlotHold::class)->findBy(['owner' => $user, 'id' => $id]) : []);
    }

    #[Route('/api/slot-holds/group/{groupId}', methods: ['DELETE'])]
    public function releaseGroup(string $groupId, #[CurrentUser] User $user): JsonResponse
    {
        return $this->remove(Uuid::isValid($groupId) ? $this->em->getRepository(SlotHold::class)->findBy(['owner' => $user, 'groupId' => $groupId]) : []);
    }

    /** @param list<SlotHold> $holds */
    private function remove(array $holds): JsonResponse
    {
        if ($holds === []) {
            return new JsonResponse(['title' => 'Ce créneau n’est plus réservé.'], Response::HTTP_NOT_FOUND);
        }
        foreach ($holds as $h) {
            $this->em->remove($h);
        }
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
