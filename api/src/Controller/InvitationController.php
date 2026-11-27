<?php

namespace App\Controller;

use App\Calendar\CalendarEventData;
use App\Calendar\CalendarProvider;
use App\Calendar\InvitationParser;
use App\Entity\CalendarAccount;
use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Mail\MailboxReaderFactory;
use App\Mail\OutgoingMailer;
use App\Repository\MailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Invitations d'agenda reçues par mail (F-25). Lire l'invitation ne change rien ; répondre (accepter, peut-être, refuser) est un clic de
 * l'utilisateur : la réponse part à l'organisateur et, si on accepte, l'événement entre dans l'agenda choisi.
 */
final class InvitationController extends AbstractController
{
    private const PARTSTAT = ['accepted' => 'ACCEPTED', 'tentative' => 'TENTATIVE', 'declined' => 'DECLINED'];

    public function __construct(
        private readonly MailRepository $mails,
        private readonly MailboxReaderFactory $factory,
        private readonly InvitationParser $parser,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/mails/{id}/invitation', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $mail = $this->mine($id, $user);
        if ($mail === null) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        try {
            $ics = $this->factory->create($mail->getAccount())->invitation($mail->getFolder(), $mail->getUid());
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. Réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['invitation' => $ics === null ? null : $this->parser->parse($ics), 'response' => $mail->getInvitationResponse()]);
    }

    #[Route('/api/mails/{id}/invitation/reply', methods: ['POST'])]
    public function reply(string $id, Request $r, #[CurrentUser] User $user, OutgoingMailer $mailer, CalendarProvider $provider): JsonResponse
    {
        $mail = $this->mine($id, $user);
        if ($mail === null) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        $in = $r->toArray();
        $response = (string) ($in['response'] ?? '');
        if (!isset(self::PARTSTAT[$response])) {
            return new JsonResponse(['title' => 'Choisis : accepter, peut-être ou refuser.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // `send: false` : inscrire dans son agenda sans prévenir personne. `message` : le texte relu par l'utilisateur.
        $send = ($in['send'] ?? true) !== false;
        $message = \is_string($in['message'] ?? null) ? trim($in['message']) : '';
        if (mb_strlen($message) > 5000) {
            return new JsonResponse(['title' => 'Ta réponse est trop longue.', 'field' => 'message'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$send && ($response === 'declined' || ($in['calendarId'] ?? '') === '')) {
            return new JsonResponse(['title' => 'Sans réponse à envoyer, il faut un agenda où inscrire l’événement.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $ics = $this->factory->create($mail->getAccount())->invitation($mail->getFolder(), $mail->getUid());
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Le serveur de messagerie ne répond pas. Rien n’a été envoyé, réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }
        $inv = $ics === null ? null : $this->parser->parse($ics);
        if ($inv === null) {
            return new JsonResponse(['title' => 'Ce message ne contient pas d’invitation que je sache lire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $calendar = null;
        if ($response !== 'declined' && ($in['calendarId'] ?? '') !== '') {
            $calendar = Uuid::isValid((string) $in['calendarId']) ? $this->em->find(CalendarAccount::class, (string) $in['calendarId']) : null;
            if ($calendar === null || $calendar->getOwner()?->getId() !== $user->getId()) {
                return new JsonResponse(['title' => 'Agenda inconnu.'], Response::HTTP_NOT_FOUND);
            }
        }
        if ($send) {
            $account = $mail->getAccount();
            $labels = ['accepted' => 'Accepté', 'tentative' => 'Peut-être', 'declined' => 'Refusé'];
            $email = (new Email())
                ->from(new Address($account->getAddress(), $user->getDisplayName()))
                ->to(new Address($inv['organizerAddress']))
                ->subject($labels[$response].' : '.$inv['title'])
                ->text($message !== '' ? $message : $user->getDisplayName().' a répondu « '.mb_strtolower($labels[$response]).' » à l’invitation « '.$inv['title']."».\n")
                ->attach($this->parser->reply($inv, $account->getAddress(), $user->getDisplayName(), self::PARTSTAT[$response]), 'reponse.ics', 'text/calendar; method=REPLY; charset=UTF-8');
            try {
                $mailer->send($account, $email);
            } catch (\Throwable) {
                return new JsonResponse(['title' => 'La réponse n’est pas partie : le serveur d’envoi ne répond pas. Rien n’a changé, réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
            }
            // La réponse partie se retrouve dans Envoyés ; un échec de cette copie ne fait pas croire qu'elle n'est pas partie.
            try {
                $this->factory->create($account)->append('Sent', $email->toString());
            } catch (\Throwable) {
            }
        }
        // Déjà inscrit (réponse changée, synchro passée par là) : il est bien dans l'agenda.
        $existing = $this->em->getRepository(CalendarEvent::class)->findOneBy(['owner' => $user, 'uid' => $inv['uid']]);
        $eventId = null;
        if ($calendar !== null && $existing === null) {
            try {
                $event = (new CalendarEvent())->setOwner($user)->setCalendar($calendar)->setUid($inv['uid'])->setTitle($inv['title'])
                    ->setStart(new \DateTimeImmutable($inv['start']))->setEnd(new \DateTimeImmutable($inv['end']))->setLocation($inv['location']);
                $provider->create($calendar, new CalendarEventData($event->getUid(), $event->getTitle(), $event->getStart(), $event->getEnd(), $event->getLocation()));
                $this->em->persist($event);
                $eventId = $event->getId();
            } catch (\Throwable) {
                $eventId = null; // la réponse est partie ; l'événement pourra être ajouté à la main
            }
        }
        if ($send) {
            $mail->setInvitationResponse($response)->markAnswered(new \DateTimeImmutable());
        } elseif ($eventId === null && $existing === null) {
            // Rien n'est parti : si l'agenda refuse, il ne s'est rien passé, et on le dit.
            return new JsonResponse(['title' => 'Le serveur d’agenda a refusé l’événement. Rien n’a été ajouté, réessaie dans un instant.'], Response::HTTP_BAD_GATEWAY);
        }
        $this->em->flush();

        // `added` : inscrit à l'instant ; `inCalendar` : il y est, que ce soit maintenant ou depuis une réponse précédente.
        return new JsonResponse(['response' => $response, 'eventId' => $eventId, 'added' => $eventId !== null, 'inCalendar' => $response !== 'declined' && ($eventId !== null || $existing !== null)]);
    }

    private function mine(string $id, User $user): ?\App\Entity\Mail
    {
        $mail = preg_match('/^[0-9a-f-]{36}$/', $id) ? $this->mails->find($id) : null;

        return $mail !== null && $mail->getOwner()?->getId() === $user->getId() ? $mail : null;
    }
}
