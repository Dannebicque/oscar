<?php

namespace App\Controller;

use App\Entity\MessageTemplate;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Modèles de message (F-19), propres à l'utilisateur. */
final class MessageTemplateController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/templates', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(array_map(static fn (MessageTemplate $t) => $t->toArray(), $this->em->getRepository(MessageTemplate::class)->findBy(['owner' => $user], ['name' => 'ASC'])));
    }

    #[Route('/api/templates', methods: ['POST'])]
    public function create(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        [$name, $body, $err] = $this->read($r);
        if ($err !== null) {
            return $err;
        }
        if ($this->em->getRepository(MessageTemplate::class)->count(['owner' => $user]) >= 50) {
            return new JsonResponse(['title' => 'Tu as atteint 50 modèles. Supprime-en un avant d’en ajouter.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $t = new MessageTemplate($user, $name, $body);
        $this->em->persist($t);
        $this->em->flush();

        return new JsonResponse($t->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/templates/{id}', methods: ['PUT'])]
    public function update(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $t = $this->mine($id, $user);
        if ($t === null) {
            return new JsonResponse(['title' => 'Modèle inconnu'], Response::HTTP_NOT_FOUND);
        }
        [$name, $body, $err] = $this->read($r);
        if ($err !== null) {
            return $err;
        }
        $t->setName($name)->setBody($body);
        $this->em->flush();

        return new JsonResponse($t->toArray());
    }

    #[Route('/api/templates/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $t = $this->mine($id, $user);
        if ($t === null) {
            return new JsonResponse(['title' => 'Modèle inconnu'], Response::HTTP_NOT_FOUND);
        }
        $this->em->remove($t);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array{string, string, ?JsonResponse} */
    private function read(Request $r): array
    {
        $in = $r->toArray();
        $name = trim((string) ($in['name'] ?? ''));
        $body = (string) ($in['body'] ?? '');
        if ($name === '' || mb_strlen($name) > 80) {
            return ['', '', new JsonResponse(['title' => 'Donne un nom au modèle (80 caractères au plus).', 'field' => 'name'], Response::HTTP_UNPROCESSABLE_ENTITY)];
        }
        if (trim($body) === '' || mb_strlen($body) > 5000) {
            return ['', '', new JsonResponse(['title' => 'Le texte du modèle est vide ou dépasse 5 000 caractères.', 'field' => 'body'], Response::HTTP_UNPROCESSABLE_ENTITY)];
        }

        return [$name, $body, null];
    }

    private function mine(string $id, User $user): ?MessageTemplate
    {
        $t = preg_match('/^[0-9a-f-]{36}$/', $id) ? $this->em->find(MessageTemplate::class, $id) : null;

        return $t !== null && $t->getOwner()->getId() === $user->getId() ? $t : null;
    }
}
