<?php

namespace App\Controller;

use App\Mail\MailboxReaderFactory;
use App\Repository\MailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Archiver, mettre à la corbeille, remettre en boîte de réception ou classer (F-16). Le déplacement se fait d'abord sur le serveur IMAP ;
 * la base ne change que s'il a réussi. Rien n'est jamais supprimé définitivement : la corbeille se vide côté serveur.
 */
final class MailMoveController extends AbstractController
{
    private const FOLDERS = ['INBOX', 'Archive', 'Trash'];

    #[Route('/api/mails/{id}/move', name: 'api_mail_move', methods: ['POST'])]
    public function __invoke(string $id, Request $request, MailRepository $mails, MailboxReaderFactory $factory, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $mail = $mails->find($id);
        if ($mail === null || $mail->getOwner()?->getId() !== $security->getUser()?->getId()) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        $target = (string) (json_decode($request->getContent(), true)['folder'] ?? '');
        $reader = null;
        if (!\in_array($target, self::FOLDERS, true)) {
            // Un dossier personnel (ou sous-dossier) : il doit exister sur le serveur, jamais créé au vol par une faute de frappe.
            if ($target === '' || MailFolderController::isSystem($target)) {
                return new JsonResponse(['title' => 'Dossier inconnu. Choisis un dossier de la liste.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            try {
                $reader = $factory->create($mail->getAccount());
                $known = array_filter($reader->folders(), static fn (array $f) => $f['path'] === $target) !== [];
            } catch (\Throwable) {
                return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. Rien n’a bougé, réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
            }
            if (!$known) {
                return new JsonResponse(['title' => 'Ce dossier n’existe pas sur le serveur de messagerie.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        if ($mail->getFolder() === 'Sent' || $mail->getFolder() === $target) {
            return new JsonResponse(['title' => 'Ce message est déjà là, ou c’est un message envoyé.'], Response::HTTP_CONFLICT);
        }
        try {
            $uid = ($reader ?? $factory->create($mail->getAccount()))->move($mail->getFolder(), $mail->getUid(), $mail->getMessageId(), $target);
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. Rien n’a bougé, réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }
        if ($uid === null) {
            return new JsonResponse(['title' => 'Le message a bougé mais je n’arrive pas à le retrouver. Resynchronise la boîte.'], Response::HTTP_BAD_GATEWAY);
        }
        $mail->relocate($target, $uid);
        $em->flush();

        return new JsonResponse(['id' => $mail->getId(), 'folder' => $target]);
    }
}
