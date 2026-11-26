<?php

namespace App\Controller;

use App\Mail\MailboxFlags;
use App\Mail\MailboxReaderFactory;
use App\Repository\MailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Marquer un mail lu ou non lu (F-16). Comme pour un déplacement : le serveur de messagerie d'abord, la base ensuite. */
final class MailSeenController extends AbstractController
{
    #[Route('/api/mails/{id}/seen', name: 'api_mail_seen', methods: ['POST'])]
    public function __invoke(string $id, Request $request, MailRepository $mails, MailboxReaderFactory $factory, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $mail = $mails->find($id);
        if ($mail === null || $mail->getOwner()?->getId() !== $security->getUser()?->getId()) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        $seen = json_decode($request->getContent(), true)['seen'] ?? null;
        if (!\is_bool($seen)) {
            return new JsonResponse(['title' => 'Indique « seen » : true ou false.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $reader = $factory->create($mail->getAccount());
            if ($reader instanceof MailboxFlags) {
                $reader->setSeen($mail->getFolder(), $mail->getUid(), $seen);
            }
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. L’état du message n’a pas changé.'], Response::HTTP_BAD_GATEWAY);
        }
        $mail->setSeen($seen);
        $em->flush();

        return new JsonResponse(['id' => $mail->getId(), 'seen' => $seen]);
    }
}
