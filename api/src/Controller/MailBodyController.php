<?php

namespace App\Controller;

use App\Mail\MailboxReaderFactory;
use App\Repository\MailRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Corps d'un mail, lu à la demande sur le serveur IMAP, en texte brut seulement. Réservé au propriétaire. */
final class MailBodyController extends AbstractController
{
    #[Route('/api/mails/{id}/body', name: 'api_mail_body', methods: ['GET'])]
    public function __invoke(string $id, MailRepository $mails, MailboxReaderFactory $factory, Security $security): JsonResponse
    {
        $mail = $mails->find($id);
        if ($mail === null || $mail->getOwner()?->getId() !== $security->getUser()?->getId()) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        $extra = [];
        try {
            $reader = $factory->create($mail->getAccount());
            $text = $reader->fetchBody($mail->getFolder(), $mail->getUid());
            // Un brouillon reprend aussi ses copies (Cc, Cci) : la liste des mails ne les garde pas.
            if ($mail->getFolder() === 'Drafts') {
                $extra = $reader->draftRecipients('Drafts', $mail->getUid());
            }
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. Réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }
        $paragraphs = $text === null ? [] : array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $text) ?: [])));

        return new JsonResponse(['paragraphs' => $paragraphs] + $extra);
    }
}
