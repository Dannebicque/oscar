<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Suivi;
use App\Entity\User;
use App\Enum\SuiviStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Boîte partagée (F-67 à F-69) : prendre, libérer, traiter, reprendre ; notes internes ; indicateur « X rédige ».
 * Aucun responsable n'est désigné : « pris par X » informe les autres membres et évite les doublons.
 * Tout membre de l'alias peut agir (réponse du cahier des charges, §9).
 */
final class TeamController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/suivis/{id}/claim', methods: ['POST'])]
    public function claim(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        if (($s = $this->teamSuivi($id, $user)) === null) {
            return $this->notFound();
        }
        $action = (string) ($r->toArray()['action'] ?? '');
        $taker = $s->getTakenBy();
        $byOther = $taker !== null && $taker->getId() !== $user->getId();
        $me = $user->getDisplayName();

        switch ($action) {
            case 'take':
                if ($s->getStatus() === SuiviStatus::Done) {
                    return $this->conflict('Cette demande est déjà traitée. Rouvre-la d’abord.', $s);
                }
                if ($byOther && $s->getStatus() === SuiviStatus::InProgress) {
                    // Anti-doublon : on prévient, sans bloquer l'équipe (reprendre est un geste explicite).
                    return $this->conflict($taker->getDisplayName().' s’en occupe. Tu peux reprendre la demande si besoin.', $s);
                }
                $s->setTakenBy($user)->setStatus(SuiviStatus::InProgress)->log($me, 'take');
                break;
            case 'release':
                $s->setTakenBy(null)->setComposing(null)->setStatus(SuiviStatus::New)->log($me, 'release');
                break;
            case 'handle':
                $s->setTakenBy($user)->setComposing(null)->setStatus(SuiviStatus::Done)->log($me, 'handled');
                break;
            case 'reopen':
                if ($s->getStatus() !== SuiviStatus::Done) {
                    return $this->conflict('Cette demande n’est pas traitée.', $s);
                }
                $s->setTakenBy($user)->setStatus(SuiviStatus::InProgress)->log($me, 'reopen');
                break;
            case 'takeOver':
                if (!$byOther) {
                    return $this->conflict('Personne d’autre n’a pris cette demande.', $s);
                }
                $previous = $taker;
                $s->setTakenBy($user)->setStatus(SuiviStatus::InProgress)->log($me, 'takeOver:'.$previous->getDisplayName());
                // L'ancien responsable en est informé (F-67).
                $this->em->persist((new Notification('takenOver', ['who' => $me, 'subject' => $s->getSubject()], 'suivis', 'takeover:'.$s->getId().':'.bin2hex(random_bytes(4))))->setOwner($previous));
                break;
            default:
                return new JsonResponse(['title' => 'Action inconnue.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->em->flush();

        return new JsonResponse($this->summary($s));
    }

    #[Route('/api/suivis/{id}/notes', methods: ['POST'])]
    public function note(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        if (($s = $this->teamSuivi($id, $user)) === null) {
            return $this->notFound();
        }
        $text = trim((string) ($r->toArray()['text'] ?? ''));
        if ($text === '' || mb_strlen($text) > 2000) {
            return new JsonResponse(['title' => 'La note est vide ou trop longue.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $s->addNote($user->getDisplayName(), $text);
        $this->em->flush();

        return new JsonResponse(['notes' => $s->getNotes()], Response::HTTP_CREATED);
    }

    /** « X rédige une réponse » (F-68) : l'indicateur expire seul au bout de deux minutes. */
    #[Route('/api/suivis/{id}/composing', methods: ['POST'])]
    public function composing(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        if (($s = $this->teamSuivi($id, $user)) === null) {
            return $this->notFound();
        }
        $other = $s->getComposingName();
        $mine = $s->getComposingBy()?->getId() === $user->getId();
        if (($r->toArray()['active'] ?? true) === false) {
            if ($mine) {
                $s->setComposing(null);
            }
        } elseif ($other === null || $mine) {
            $s->setComposing($user);
        }
        $this->em->flush();

        return new JsonResponse(['composing' => $s->getComposingName(), 'other' => $other !== null && !$mine ? $other : null]);
    }

    /** Une demande d'alias n'est visible, et donc modifiable, que par les membres de l'alias. */
    private function teamSuivi(string $id, User $user): ?Suivi
    {
        $s = $this->em->find(Suivi::class, $id);
        $box = $s?->getSharedMailbox();

        return $s !== null && $box !== null && $box->memberOf($user) !== null ? $s : null;
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(['title' => 'Demande inconnue.'], Response::HTTP_NOT_FOUND);
    }

    private function conflict(string $message, Suivi $s): JsonResponse
    {
        return new JsonResponse(['title' => $message] + $this->summary($s), Response::HTTP_CONFLICT);
    }

    /** @return array<string, mixed> */
    private function summary(Suivi $s): array
    {
        return ['status' => $s->getStatus()->value, 'takenByName' => $s->getTakenByName(), 'composing' => $s->getComposingName()];
    }
}
