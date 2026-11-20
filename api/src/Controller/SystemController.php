<?php

namespace App\Controller;

use App\Entity\CalendarAccount;
use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Barre d'état des connexions (F-77) et notifications (F-75). */
final class SystemController extends AbstractController
{
    #[Route('/api/connections', methods: ['GET'])]
    public function connections(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        $out = [];
        $mail = $em->getRepository(MailAccount::class)->findBy(['owner' => $user]);
        if ($mail !== []) {
            $out[] = $this->aggregate('mail', $mail);
        }
        $cal = $em->getRepository(CalendarAccount::class)->findBy(['owner' => $user]);
        foreach (['caldav' => 'calendar', 'google' => 'google'] as $kind => $id) {
            $of = array_values(array_filter($cal, static fn (CalendarAccount $a) => $a->getKind() === $kind));
            if ($of !== []) {
                $out[] = $this->aggregate($id, $of);
            }
        }
        $mode = ($em->getRepository(UserSettings::class)->findOneBy(['owner' => $user]) ?? new UserSettings($user))->all()['aiMode'];
        $out[] = ['id' => 'ai', 'state' => 'ok', 'detail' => (string) $mode, 'lastOk' => null];

        return new JsonResponse($out);
    }

    #[Route('/api/notifications/read-all', methods: ['POST'])]
    public function readAll(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        foreach ($em->getRepository(Notification::class)->findBy(['owner' => $user, 'read' => false]) as $n) {
            $n->markRead();
        }
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @param list<MailAccount|CalendarAccount> $accounts : le pire état l'emporte ; la dernière réussite est la plus récente. */
    private function aggregate(string $id, array $accounts): array
    {
        $failed = array_values(array_filter($accounts, static fn ($a) => $a->getLastError() !== null));
        $lastOk = null;
        foreach ($accounts as $a) {
            $d = $a->getLastSyncAt();
            if ($d !== null && ($lastOk === null || $d > $lastOk)) {
                $lastOk = $d;
            }
        }

        return [
            'id' => $id, 'state' => $failed !== [] ? 'error' : 'ok',
            'detail' => implode(', ', array_map(static fn ($a) => $a->getLabel(), $accounts)),
            'lastOk' => $lastOk?->format(\DATE_ATOM),
        ];
    }
}
