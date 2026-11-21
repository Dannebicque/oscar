<?php

namespace App\Controller;

use App\Mail\MailboxReaderFactory;
use App\Repository\MailRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pièces jointes d'un mail (F-16). Un mail est une donnée non fiable : une pièce jointe n'est JAMAIS affichée dans l'application,
 * seulement téléchargée (type forcé, pas de sniffing). Réservé au propriétaire.
 */
final class MailAttachmentController extends AbstractController
{
    #[Route('/api/mails/{id}/attachments', name: 'api_mail_attachments', methods: ['GET'])]
    public function list(string $id, MailRepository $mails, MailboxReaderFactory $factory, Security $security): JsonResponse
    {
        $mail = $mails->find($id);
        if ($mail === null || $mail->getOwner()?->getId() !== $security->getUser()?->getId()) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        try {
            $list = $factory->create($mail->getAccount())->attachments($mail->getFolder(), $mail->getUid());
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. Réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['attachments' => $list]);
    }

    #[Route('/api/mails/{id}/attachments/{index}', name: 'api_mail_attachment', methods: ['GET'], requirements: ['index' => '\d+'])]
    public function download(string $id, int $index, MailRepository $mails, MailboxReaderFactory $factory, Security $security): Response
    {
        $mail = $mails->find($id);
        if ($mail === null || $mail->getOwner()?->getId() !== $security->getUser()?->getId()) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        try {
            $a = $factory->create($mail->getAccount())->attachment($mail->getFolder(), $mail->getUid(), $index);
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. Réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }
        if ($a === null) {
            return new JsonResponse(['title' => 'Pièce jointe introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $r = new Response($a['content']);
        $r->headers->set('Content-Type', 'application/octet-stream');
        $r->headers->set('X-Content-Type-Options', 'nosniff');
        $r->headers->set('Content-Disposition', $r->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, preg_replace('/[^\w.\- ]+/u', '_', $a['name']) ?: 'piece-jointe'));

        return $r;
    }
}
