<?php

namespace App\Controller;

use App\Entity\Identity;
use App\Entity\MailAccount;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Identités d'expéditeur et signatures (F-19), propres à l'utilisateur. Chaque compte a une identité principale créée à la première lecture ;
 * on peut en ajouter pour un alias ou une fonction. Une seule identité par défaut. L'identité principale ne se supprime pas.
 */
final class IdentityController extends AbstractController
{
    private const MAX = 20;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/identities', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $this->ensurePrimaries($user);

        return new JsonResponse(array_map(static fn (Identity $i) => $i->toArray(), $this->all($user)));
    }

    #[Route('/api/identities', methods: ['POST'])]
    public function create(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $this->ensurePrimaries($user);
        $in = $r->toArray();
        $account = $this->em->find(MailAccount::class, (string) ($in['accountId'] ?? ''));
        if ($account === null || $account->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['title' => 'Compte de messagerie inconnu.', 'field' => 'accountId'], Response::HTTP_NOT_FOUND);
        }
        if (\count($this->all($user)) >= self::MAX) {
            return new JsonResponse(['title' => 'Tu as atteint '.self::MAX.' identités. Supprime-en une avant d’en ajouter.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($err = $this->validate($in, true)) {
            return $err;
        }
        $i = new Identity($user, $account, trim((string) $in['name']), strtolower(trim((string) $in['address'])), $in['replyTo'] ?? null, $in['signature'] ?? null);
        $i->setReplyTo($in['replyTo'] ?? null);
        $this->em->persist($i);
        $this->em->flush();

        return new JsonResponse($i->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/identities/{id}', methods: ['PUT'])]
    public function update(string $id, Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $i = $this->mine($id, $user);
        if ($i === null) {
            return new JsonResponse(['title' => 'Identité inconnue.'], Response::HTTP_NOT_FOUND);
        }
        $in = $r->toArray();
        if ($err = $this->validate($in, !$i->isPrimary())) {
            return $err;
        }
        // L'adresse de l'identité principale est celle du compte : on ne la change pas ici.
        $i->setName(trim((string) $in['name']))->setReplyTo($in['replyTo'] ?? null)->setSignature($in['signature'] ?? null);
        if (!$i->isPrimary()) {
            $i->setAddress(strtolower(trim((string) $in['address'])));
        }
        $this->em->flush();

        return new JsonResponse($i->toArray());
    }

    #[Route('/api/identities/{id}/default', methods: ['POST'])]
    public function makeDefault(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $i = $this->mine($id, $user);
        if ($i === null) {
            return new JsonResponse(['title' => 'Identité inconnue.'], Response::HTTP_NOT_FOUND);
        }
        foreach ($this->all($user) as $x) {
            $x->setDefault($x->getId() === $i->getId());
        }
        $this->em->flush();

        return new JsonResponse($i->toArray());
    }

    #[Route('/api/identities/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $i = $this->mine($id, $user);
        if ($i === null) {
            return new JsonResponse(['title' => 'Identité inconnue.'], Response::HTTP_NOT_FOUND);
        }
        if ($i->isPrimary()) {
            return new JsonResponse(['title' => 'L’identité principale d’un compte ne se supprime pas : elle suit l’adresse du compte.'], Response::HTTP_CONFLICT);
        }
        $wasDefault = $i->isDefault();
        $account = $i->getAccount();
        $this->em->remove($i);
        $this->em->flush();
        if ($wasDefault) {
            // La principale du même compte reprend le rôle d'identité par défaut.
            foreach ($this->all($user) as $x) {
                if ($x->isPrimary() && $x->getAccount()->getId() === $account->getId()) {
                    $x->setDefault(true);
                    break;
                }
            }
            $this->em->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @return list<Identity> */
    private function all(User $user): array
    {
        return $this->em->getRepository(Identity::class)->findBy(['owner' => $user], ['primary' => 'DESC', 'name' => 'ASC']);
    }

    private function mine(string $id, User $user): ?Identity
    {
        $i = $this->em->find(Identity::class, $id);

        return $i !== null && $i->getOwner()->getId() === $user->getId() ? $i : null;
    }

    /** Chaque compte a son identité principale : nom de l'utilisateur, adresse et signature du compte. */
    private function ensurePrimaries(User $user): void
    {
        $have = array_map(static fn (Identity $i) => $i->getAccount()->getId(), array_filter($this->all($user), static fn (Identity $i) => $i->isPrimary()));
        $hasDefault = array_filter($this->all($user), static fn (Identity $i) => $i->isDefault()) !== [];
        $created = false;
        foreach ($this->em->getRepository(MailAccount::class)->findBy(['owner' => $user], ['id' => 'ASC']) as $a) {
            if (\in_array($a->getId(), $have, true)) {
                continue;
            }
            $i = new Identity($user, $a, $user->getDisplayName(), strtolower($a->getAddress()), null, $a->getSignature(), true);
            if (!$hasDefault) {
                $i->setDefault(true);
                $hasDefault = true;
            }
            $this->em->persist($i);
            $created = true;
        }
        if ($created) {
            $this->em->flush();
        }
    }

    /** @param array<string, mixed> $in */
    private function validate(array $in, bool $withAddress): ?JsonResponse
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100 || preg_match('/[\r\n<>"]/', $name)) {
            return new JsonResponse(['title' => 'Donne un nom de 1 à 100 caractères, sans < > ni guillemets.', 'field' => 'name'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($withAddress && (!filter_var(trim((string) ($in['address'] ?? '')), \FILTER_VALIDATE_EMAIL) || mb_strlen((string) $in['address']) > 180)) {
            return new JsonResponse(['title' => 'Cette adresse n’est pas valide.', 'field' => 'address'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $reply = trim((string) ($in['replyTo'] ?? ''));
        if ($reply !== '' && (!filter_var($reply, \FILTER_VALIDATE_EMAIL) || mb_strlen($reply) > 180)) {
            return new JsonResponse(['title' => 'L’adresse « Répondre à » n’est pas valide.', 'field' => 'replyTo'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (isset($in['signature']) && (!\is_string($in['signature']) || mb_strlen($in['signature']) > 2000)) {
            return new JsonResponse(['title' => 'La signature ne doit pas dépasser 2 000 caractères.', 'field' => 'signature'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }
}
