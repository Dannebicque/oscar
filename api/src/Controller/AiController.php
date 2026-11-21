<?php

namespace App\Controller;

use App\Ai\AiProviderRegistry;
use App\Entity\AiCall;
use App\Entity\CalendarEvent;
use App\Entity\Mail;
use App\Entity\Suivi;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Mail\MailboxReaderFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Suggestions de l'IA : elle propose et prépare, elle ne décide ni n'envoie (§5). */
final class AiController extends AbstractController
{
    private const NONE = ['availability' => null, 'event' => null, 'task' => null, 'suggestFollow' => false, 'summary' => null];

    public function __construct(private readonly EntityManagerInterface $em, private readonly AiProviderRegistry $registry, private readonly MailboxReaderFactory $factory)
    {
    }

    #[Route('/api/mails/{id}/hints', methods: ['GET'])]
    public function hints(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $mail = $this->em->find(Mail::class, $id);
        if ($mail === null || $mail->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        $settings = $this->settings($user);
        // L'IA est coupée, ou interdite sur cette boîte (confidentielle, RH) : aucune lecture du mail.
        if (!$mail->getAccount()->isAiAllowed() || ($mail->getSharedMailbox() !== null && !$mail->getSharedMailbox()->isAiEnabled()) || ($provider = $this->registry->for((string) $settings['aiMode'])) === null || !$settings['aiSuggestTasks'] && !$settings['aiSuggestFollow']) {
            return new JsonResponse(self::NONE);
        }
        try {
            $body = $this->factory->create($mail->getAccount())->fetchBody($mail->getFolder(), $mail->getUid()) ?? '';
        } catch (\Throwable) {
            return new JsonResponse(['title' => 'Je n’ai pas pu lire ce message pour te faire des suggestions.'], Response::HTTP_BAD_GATEWAY);
        }
        // Agenda : uniquement des créneaux occupés (début/fin). Titres, lieux et participants ne sortent jamais d'ici.
        $busy = [];
        $qb = $this->em->createQueryBuilder()->select('e', 'c')->from(CalendarEvent::class, 'e')->join('e.calendar', 'c')->where('e.owner = :u AND e.end > :from')->setParameter('u', $user->getId())->setParameter('from', $mail->getDate());
        if (!$settings['includePersonalCalendar']) {
            $qb->andWhere('c.personal = false');
        }
        foreach ($qb->getQuery()->getResult() as $e) {
            $busy[] = ['start' => $e->getStart(), 'end' => $e->getEnd()];
        }
        $a = $provider->analyse($mail->getSubject(), $body, $mail->getDate()->setTimezone(new \DateTimeZone((string) $settings['timezone'])), $busy);
        $out = $a->toArray();
        if (!$settings['aiSuggestTasks']) {
            $out['task'] = $out['event'] = $out['availability'] = $out['summary'] = null;
        }
        if (!$settings['aiSuggestFollow']) {
            $out['suggestFollow'] = false;
        }
        $this->em->persist((new AiCall($provider->name(), 'mail-hints', sprintf('mail %s, objet de %d caractères, %d créneaux occupés', $mail->getId(), mb_strlen($mail->getSubject()), \count($busy)), $out))->setOwner($user));
        $this->em->flush();

        return new JsonResponse($out);
    }

    /** Brouillon de réponse à un mail : l'utilisateur choisit le ton et les créneaux, relit, puis envoie lui-même. */
    #[Route('/api/mails/{id}/reply-draft', methods: ['POST'])]
    public function replyDraft(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $mail = $this->em->find(Mail::class, $id);
        if ($mail === null || $mail->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['title' => 'Mail inconnu'], Response::HTTP_NOT_FOUND);
        }
        $settings = $this->settings($user);
        $provider = $this->registry->for((string) $settings['aiMode']);
        if ($provider === null || !$mail->getAccount()->isAiAllowed() || ($mail->getSharedMailbox() !== null && !$mail->getSharedMailbox()->isAiEnabled())) {
            return new JsonResponse(['title' => 'Le brouillon par l’IA est désactivé pour cette boîte.'], Response::HTTP_FORBIDDEN);
        }
        $in = json_decode($request->getContent(), true) ?: [];
        $tone = ($in['tone'] ?? 'warm') === 'short' ? 'short' : 'warm';
        $lines = array_values(array_filter(array_map(static fn ($l) => \is_string($l) ? mb_substr(trim($l), 0, 120) : '', (array) ($in['slots'] ?? [])), static fn ($l) => $l !== ''));
        $body = $provider->draftReply($mail->getFromName() ?: $mail->getFromAddress(), $mail->getSubject(), $user->getDisplayName(), (string) $settings['locale'], $tone, \array_slice($lines, 0, 8));
        $this->em->persist((new AiCall($provider->name(), 'reply-draft', 'mail '.$mail->getId(), ['chars' => mb_strlen($body), 'slots' => \count($lines)]))->setOwner($user));
        $this->em->flush();

        return new JsonResponse(['body' => $body, 'ai' => true]);
    }

    #[Route('/api/suivis/{id}/relance-draft', methods: ['POST'])]
    public function relanceDraft(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $s = $this->em->find(Suivi::class, $id);
        if ($s === null || $s->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['title' => 'Suivi inconnu'], Response::HTTP_NOT_FOUND);
        }
        $settings = $this->settings($user);
        $provider = $this->registry->for((string) $settings['aiMode']);
        if ($provider === null || !$settings['aiDraftRelance']) {
            return new JsonResponse(['title' => 'Le brouillon par l’IA est désactivé dans Réglages.'], Response::HTTP_FORBIDDEN);
        }
        $body = $provider->draftRelance($s->getContact(), $s->getSubject(), $user->getDisplayName(), (string) $settings['locale']);
        $this->em->persist((new AiCall($provider->name(), 'relance-draft', 'suivi '.$s->getId(), ['chars' => mb_strlen($body)]))->setOwner($user));
        $this->em->flush();

        return new JsonResponse(['body' => $body, 'ai' => true]);
    }

    /** @return array<string, scalar> */
    private function settings(User $user): array
    {
        return ($this->em->getRepository(UserSettings::class)->findOneBy(['owner' => $user]) ?? new UserSettings($user))->all();
    }
}
