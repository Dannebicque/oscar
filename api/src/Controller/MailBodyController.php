<?php

namespace App\Controller;

use App\Mail\MailboxFlags;
use App\Mail\MailHtml;
use App\Mail\MailboxReaderFactory;
use App\Repository\MailRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Corps d'un mail, lu à la demande sur le serveur IMAP. Réservé au propriétaire. Le texte toujours ; la mise en forme quand il y en a
 * une, reconstruite par MailHtml (F-16). Les images distantes ne sont gardées que sur demande (`?images=1`), jamais mémorisées.
 */
final class MailBodyController extends AbstractController
{
    #[Route('/api/mails/{id}/body', name: 'api_mail_body', methods: ['GET'])]
    public function __invoke(string $id, Request $request, MailRepository $mails, MailboxReaderFactory $factory, MailHtml $mailHtml, Security $security): JsonResponse
    {
        $mail = $mails->find($id);
        if ($mail === null || $mail->getOwner()?->getId() !== $security->getUser()?->getId()) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        $extra = [];
        try {
            $reader = $factory->create($mail->getAccount());
            $content = $reader->fetchContent($mail->getFolder(), $mail->getUid());
            // « Pas de texte » et « plus là » ne se disent pas pareil.
            if ($content === null && $reader instanceof MailboxFlags && !\in_array($mail->getUid(), $reader->uids($mail->getFolder()), true)) {
                return new JsonResponse(['title' => 'Ce message n’est plus sur ton serveur de messagerie : supprimé ou déplacé ailleurs.'], Response::HTTP_GONE);
            }
            // Un brouillon reprend aussi ses copies (Cc, Cci) : la liste des mails ne les garde pas.
            if ($mail->getFolder() === 'Drafts') {
                $extra = $reader->draftRecipients('Drafts', $mail->getUid());
            }
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. Réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }
        $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $content?->text ?? '') ?: [])));
        // Un brouillon se reprend en texte : seule la rédaction le lit.
        $rich = $content?->html !== null && $mail->getFolder() !== 'Drafts'
            ? $mailHtml->render($content->html, $content->inline, $request->query->getBoolean('images'))
            : null;

        return new JsonResponse(['paragraphs' => $paragraphs, 'html' => $rich['html'] ?? null, 'blockedImages' => $rich['blockedImages'] ?? 0] + $extra);
    }
}
