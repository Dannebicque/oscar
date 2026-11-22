<?php

namespace App\Controller;

use App\Entity\MailAccount;
use App\Entity\ScheduledMail;
use App\Entity\User;
use App\Mail\ScheduleMailRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Mails programmés (F-73) : l'utilisateur choisit l'heure d'envoi ; tant qu'elle n'est pas venue, il peut annuler. */
final class ScheduledMailController extends AbstractController
{
    #[Route('/api/scheduled-mails', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        $rows = $em->getRepository(ScheduledMail::class)->findBy(['owner' => $user], ['sendAt' => 'ASC']);

        return new JsonResponse(array_map(static fn (ScheduledMail $s) => $s->toArray(), $rows));
    }

    #[Route('/api/scheduled-mails', methods: ['POST'])]
    public function create(#[MapRequestPayload] ScheduleMailRequest $r, #[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        $account = $em->find(MailAccount::class, $r->mail->accountId);
        if ($account === null || $account->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['title' => 'Compte de messagerie inconnu.'], Response::HTTP_NOT_FOUND);
        }
        try {
            $at = new \DateTimeImmutable($r->sendAt);
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Heure d’envoi invalide.', 'field' => 'sendAt'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $now = new \DateTimeImmutable();
        if ($at < $now->modify('-1 minute') || $at > $now->modify('+90 days')) {
            return new JsonResponse(['title' => 'Choisis une heure à venir, dans les 90 jours.', 'field' => 'sendAt'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $total = array_sum(array_map(static fn ($a) => (int) (\strlen((string) ($a['content'] ?? '')) * 0.75), $r->mail->attachments));
        if ($total > $account->attachmentLimitBytes()) {
            return new JsonResponse(['title' => sprintf('Les pièces jointes dépassent %d Mo, la limite de ce compte. Retire-en une ou envoie un lien.', $account->attachmentLimitBytes() / 1048576), 'limitMb' => (int) ($account->attachmentLimitBytes() / 1048576)], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }
        $s = new ScheduledMail($user, get_object_vars($r->mail), $at);
        $em->persist($s);
        $em->flush();

        return new JsonResponse($s->toArray(), Response::HTTP_CREATED);
    }

    /** Annuler : le message ne partira pas. Il est rendu tel quel pour être rouvert dans l'éditeur. */
    #[Route('/api/scheduled-mails/{id}', methods: ['DELETE'])]
    public function cancel(string $id, #[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        $s = $em->find(ScheduledMail::class, $id);
        if ($s === null || $s->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['title' => 'Mail programmé introuvable.'], Response::HTTP_NOT_FOUND);
        }
        if ($s->getStatus() === ScheduledMail::SENDING) {
            return new JsonResponse(['title' => 'Il est en train de partir : trop tard pour l’annuler.'], Response::HTTP_CONFLICT);
        }
        $request = $s->getRequest();
        $em->remove($s);
        $em->flush();

        return new JsonResponse($request);
    }
}
