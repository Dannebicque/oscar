<?php

namespace App\Controller;

use App\Entity\SortRule;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Règles de tri déterministes (F-15), propres à l'utilisateur. */
final class SortRuleController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/sort-rules', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(array_map(static fn (SortRule $r) => $r->toArray(), $this->em->getRepository(SortRule::class)->findBy(['owner' => $user])));
    }

    #[Route('/api/sort-rules', methods: ['POST'])]
    public function create(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $in = $r->toArray();
        $field = (string) ($in['field'] ?? '');
        $action = (string) ($in['action'] ?? '');
        $value = mb_strtolower(trim((string) ($in['value'] ?? '')));
        if (!\in_array($field, SortRule::FIELDS, true) || !\in_array($action, SortRule::ACTIONS, true)) {
            return new JsonResponse(['title' => 'Choisis ce qui déclenche la règle et ce qu’elle fait.', 'field' => 'field'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $value = $field === 'domain' ? ltrim($value, '@') : $value;
        $ok = match ($field) {
            'sender' => (bool) filter_var($value, FILTER_VALIDATE_EMAIL),
            'domain' => (bool) preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/', $value),
            default => mb_strlen($value) >= 3 && mb_strlen($value) <= 80,
        };
        if (!$ok) {
            return new JsonResponse(['title' => $field === 'subject' ? 'Indique un mot ou une expression de 3 caractères au moins.' : ($field === 'sender' ? 'Indique une adresse complète.' : 'Indique un domaine, par exemple univ-reims.fr.'), 'field' => 'value'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($this->em->getRepository(SortRule::class)->findOneBy(['owner' => $user, 'field' => $field, 'value' => $value]) !== null) {
            return new JsonResponse(['title' => 'Cette règle existe déjà.', 'field' => 'value'], Response::HTTP_CONFLICT);
        }
        if ($this->em->getRepository(SortRule::class)->count(['owner' => $user]) >= 50) {
            return new JsonResponse(['title' => 'Tu as atteint 50 règles.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $rule = new SortRule($user, $field, $value, $action);
        $this->em->persist($rule);
        $this->em->flush();

        return new JsonResponse($rule->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/sort-rules/{id}', methods: ['PATCH'])]
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

    #[Route('/api/sort-rules/{id}', methods: ['DELETE'])]
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

    private function mine(string $id, User $user): ?SortRule
    {
        $rule = preg_match('/^[0-9a-f-]{36}$/', $id) ? $this->em->find(SortRule::class, $id) : null;

        return $rule !== null && $rule->getOwner()->getId() === $user->getId() ? $rule : null;
    }
}
