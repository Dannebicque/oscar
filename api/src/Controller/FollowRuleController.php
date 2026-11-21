<?php

namespace App\Controller;

use App\Entity\FollowRule;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Règles de création automatique de suivis (F-66) : visibles, modifiables, désactivables, propres à l'utilisateur. */
final class FollowRuleController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/follow-rules', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(array_map(static fn (FollowRule $r) => $r->toArray(), $this->em->getRepository(FollowRule::class)->findBy(['owner' => $user], ['createdAt' => 'ASC'])));
    }

    #[Route('/api/follow-rules', methods: ['POST'])]
    public function create(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $in = $r->toArray();
        $kind = (string) ($in['kind'] ?? '');
        $value = mb_strtolower(trim((string) ($in['value'] ?? '')));
        if (!\in_array($kind, FollowRule::KINDS, true)) {
            return new JsonResponse(['title' => 'Choisis « un expéditeur » ou « un domaine ».', 'field' => 'kind'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $value = $kind === 'domain' ? ltrim($value, '@') : $value;
        $ok = $kind === 'sender' ? filter_var($value, FILTER_VALIDATE_EMAIL) : preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/', $value);
        if (!$ok) {
            return new JsonResponse(['title' => $kind === 'sender' ? 'Indique une adresse complète, par exemple sophie@univ-reims.fr.' : 'Indique un domaine, par exemple univ-reims.fr.', 'field' => 'value'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($this->em->getRepository(FollowRule::class)->findOneBy(['owner' => $user, 'kind' => $kind, 'value' => $value]) !== null) {
            return new JsonResponse(['title' => 'Cette règle existe déjà.', 'field' => 'value'], Response::HTTP_CONFLICT);
        }
        if ($this->em->getRepository(FollowRule::class)->count(['owner' => $user]) >= 50) {
            return new JsonResponse(['title' => 'Tu as atteint 50 règles. Supprime-en une avant d’en ajouter.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $rule = new FollowRule($user, $kind, $value);
        $this->em->persist($rule);
        $this->em->flush();

        return new JsonResponse($rule->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/follow-rules/{id}', methods: ['PATCH'])]
    public function toggle(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $rule = $this->mine($id, $user);
        if ($rule === null) {
            return new JsonResponse(['title' => 'Règle inconnue'], Response::HTTP_NOT_FOUND);
        }
        $in = $r->toArray();
        if (isset($in['enabled']) && \is_bool($in['enabled'])) {
            $rule->setEnabled($in['enabled']);
            $this->em->flush();
        }

        return new JsonResponse($rule->toArray());
    }

    #[Route('/api/follow-rules/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $rule = $this->mine($id, $user);
        if ($rule === null) {
            return new JsonResponse(['title' => 'Règle inconnue'], Response::HTTP_NOT_FOUND);
        }
        $this->em->remove($rule);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function mine(string $id, User $user): ?FollowRule
    {
        $rule = preg_match('/^[0-9a-f-]{36}$/', $id) ? $this->em->find(FollowRule::class, $id) : null;

        return $rule !== null && $rule->getOwner()->getId() === $user->getId() ? $rule : null;
    }
}
