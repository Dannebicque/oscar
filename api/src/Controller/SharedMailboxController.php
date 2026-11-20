<?php

namespace App\Controller;

use App\Entity\SharedMailbox;
use App\Entity\SharedMailboxMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Boîtes partagées : membres et rôles (F-40). Un membre voit sa boîte ; un administrateur gère les membres et l'IA (§2, §5). */
final class SharedMailboxController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/shared-mailboxes', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $out = [];
        foreach ($this->em->getRepository(SharedMailboxMember::class)->findBy(['user' => $user]) as $m) {
            $out[] = $this->view($m->getMailbox(), $user);
        }

        return new JsonResponse($out);
    }

    #[Route('/api/shared-mailboxes', methods: ['POST'])]
    public function create(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $in = $r->toArray();
        $address = strtolower(trim((string) ($in['address'] ?? '')));
        $slug = strtolower(trim((string) ($in['slug'] ?? '')));
        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-z0-9-]{1,40}$/', $slug) || trim((string) ($in['name'] ?? '')) === '' || $slug === 'personal') {
            return new JsonResponse(['title' => 'Adresse, nom ou identifiant invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($this->em->getRepository(SharedMailbox::class)->findOneBy(['address' => $address]) !== null || $this->em->getRepository(SharedMailbox::class)->findOneBy(['slug' => $slug]) !== null) {
            return new JsonResponse(['title' => 'Cette boîte partagée existe déjà.'], Response::HTTP_CONFLICT);
        }
        $box = new SharedMailbox($address, trim((string) $in['name']), $slug);
        $box->addMember($user, 'admin');
        $this->em->persist($box);
        $this->em->flush();

        return new JsonResponse($this->view($box, $user), Response::HTTP_CREATED);
    }

    #[Route('/api/shared-mailboxes/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        if (($box = $this->adminBox($id, $user)) === null) {
            return $this->forbidden($id, $user);
        }
        $in = $r->toArray();
        isset($in['autoFollow']) && \is_bool($in['autoFollow']) && $box->setAutoFollow($in['autoFollow']);
        isset($in['aiEnabled']) && \is_bool($in['aiEnabled']) && $box->setAiEnabled($in['aiEnabled']);
        $this->em->flush();

        return new JsonResponse($this->view($box, $user));
    }

    #[Route('/api/shared-mailboxes/{id}/members', methods: ['POST'])]
    public function addMember(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        if (($box = $this->adminBox($id, $user)) === null) {
            return $this->forbidden($id, $user);
        }
        $in = $r->toArray();
        $target = $this->em->getRepository(User::class)->findOneBy(['username' => (string) ($in['username'] ?? '')]);
        $role = (string) ($in['role'] ?? 'member');
        if ($target === null || !\in_array($role, ['member', 'admin'], true)) {
            return new JsonResponse(['title' => 'Utilisateur inconnu ou rôle invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $box->addMember($target, $role)->setRole($role);
        $this->em->flush();

        return new JsonResponse($this->view($box, $user), Response::HTTP_CREATED);
    }

    #[Route('/api/shared-mailboxes/{id}/members/{username}', methods: ['DELETE'])]
    public function removeMember(string $id, string $username, #[CurrentUser] User $user): JsonResponse
    {
        if (($box = $this->adminBox($id, $user)) === null) {
            return $this->forbidden($id, $user);
        }
        $member = null;
        foreach ($box->getMembers() as $m) {
            $m->getUser()->getUsername() === $username && $member = $m;
        }
        if ($member === null) {
            return new JsonResponse(['title' => 'Ce membre n’existe pas.'], Response::HTTP_NOT_FOUND);
        }
        $admins = array_filter($box->getMembers()->toArray(), static fn (SharedMailboxMember $m) => $m->isAdmin());
        if ($member->isAdmin() && \count($admins) <= 1) {
            return new JsonResponse(['title' => 'Il faut au moins un administrateur.'], Response::HTTP_CONFLICT);
        }
        $box->getMembers()->removeElement($member);
        $this->em->flush();

        return new JsonResponse($this->view($box, $user));
    }

    private function adminBox(string $id, User $user): ?SharedMailbox
    {
        $box = $this->em->find(SharedMailbox::class, $id);

        return $box?->memberOf($user)?->isAdmin() ? $box : null;
    }

    /** 404 pour un non-membre (la boîte n'existe pas pour lui), 403 pour un membre sans droit d'administration. */
    private function forbidden(string $id, User $user): JsonResponse
    {
        $box = $this->em->find(SharedMailbox::class, $id);

        return $box?->memberOf($user) === null
            ? new JsonResponse(['title' => 'Boîte partagée inconnue.'], Response::HTTP_NOT_FOUND)
            : new JsonResponse(['title' => 'Seul un administrateur de la boîte peut faire cela.'], Response::HTTP_FORBIDDEN);
    }

    /** @return array<string, mixed> */
    private function view(SharedMailbox $b, User $me): array
    {
        return [
            'id' => $b->getId(), 'slug' => $b->getSlug(), 'address' => $b->getAddress(), 'name' => $b->getName(),
            'role' => $b->memberOf($me)?->getRole(), 'autoFollow' => $b->isAutoFollow(), 'aiEnabled' => $b->isAiEnabled(),
            'members' => array_map(static fn (SharedMailboxMember $m) => ['username' => $m->getUser()->getUsername(), 'displayName' => $m->getUser()->getDisplayName(), 'role' => $m->getRole()], $b->getMembers()->toArray()),
        ];
    }
}
