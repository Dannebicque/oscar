<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Mon compte (N-05, N-06) : changer de mot de passe, supprimer le compte et toutes ses données. L'export se fait depuis le front,
 * à partir des mêmes points d'accès que l'application (aucun secret n'en sort : mots de passe de messagerie jamais renvoyés).
 */
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TokenService $tokens,
    ) {
    }

    #[Route('/api/me/password', methods: ['POST'])]
    public function changePassword(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $in = $r->toArray();
        if ($user->getPassword() === null) {
            return new JsonResponse(['title' => 'Ton compte se connecte par l’établissement : le mot de passe se change là-bas.'], Response::HTTP_CONFLICT);
        }
        if (!$this->hasher->isPasswordValid($user, (string) ($in['current'] ?? ''))) {
            return new JsonResponse(['title' => 'Le mot de passe actuel est incorrect.', 'field' => 'current'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $new = (string) ($in['password'] ?? '');
        if (mb_strlen($new) < 10 || mb_strlen($new) > 200 || mb_strtolower($new) === mb_strtolower((string) $user->getEmail())) {
            return new JsonResponse(['title' => 'Le nouveau mot de passe doit faire au moins 10 caractères et différer de ton adresse.', 'field' => 'password'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user->setPasswordHash($this->hasher->hashPassword($user, $new));
        $this->em->flush();
        $h = (string) $r->headers->get('Authorization', '');
        if (str_starts_with($h, 'Bearer ')) {
            $this->tokens->revokeOthers($user, substr($h, 7)); // les autres appareils sont déconnectés
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Suppression définitive du compte : tout part en cascade (mails synchronisés, suivis, tâches, comptes, réglages). Les mails restent sur le serveur de messagerie. */
    #[Route('/api/me', methods: ['DELETE'])]
    public function delete(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $in = $r->toArray();
        $ok = $user->getPassword() !== null
            ? $this->hasher->isPasswordValid($user, (string) ($in['password'] ?? ''))
            : hash_equals($user->getUsername(), (string) ($in['confirm'] ?? ''));
        if (!$ok) {
            return new JsonResponse(['title' => $user->getPassword() !== null ? 'Mot de passe incorrect : le compte n’a pas été supprimé.' : 'Recopie ton identifiant pour confirmer : le compte n’a pas été supprimé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->tokens->revokeAll($user); // d'abord les sessions : la requête en cours est la dernière
        $this->em->remove($user);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
