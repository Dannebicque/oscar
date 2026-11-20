<?php

namespace App\Controller;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\User;
use App\Mail\MailSender;
use App\Mail\SendMailRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** « Envoyer » : un clic de l'utilisateur. Le suivi n'est créé que si le message est réellement parti (F-73). */
final class SendMailController extends AbstractController
{
    #[Route('/api/mails/send', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] SendMailRequest $r, #[CurrentUser] User $user, EntityManagerInterface $em, MailSender $sender): JsonResponse
    {
        $account = $em->find(MailAccount::class, $r->accountId);
        if ($account === null || $account->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['title' => 'Compte de messagerie inconnu.'], Response::HTTP_NOT_FOUND);
        }
        $reply = null;
        if ($r->inReplyToMailId !== null) {
            $reply = $em->find(Mail::class, $r->inReplyToMailId);
            if ($reply === null || $reply->getOwner()?->getId() !== $user->getId()) {
                return new JsonResponse(['title' => 'Le message auquel tu réponds est introuvable.'], Response::HTTP_NOT_FOUND);
            }
        }
        try {
            $result = $sender->send($user, $account, $r, $reply);
        } catch (\Throwable) {
            // Message clair et action proposée ; aucun suivi n'a été créé (F-79).
            return new JsonResponse(['title' => 'Le message n’est pas parti : le serveur d’envoi ne répond pas ou refuse. Rien n’a été créé, réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'messageId' => $result['messageId'], 'savedToSent' => $result['savedToSent'],
            'suivi' => $result['suivi'] === null ? null : ['id' => $result['suivi']->getId(), 'dueAt' => $result['suivi']->getDueAt()->format('Y-m-d')],
        ], Response::HTTP_CREATED);
    }
}
