<?php

namespace App\Controller;

use App\Entity\User;
use App\Instance\InstanceConfig;
use App\Instance\SetupToken;
use App\Security\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Assistant de premier lancement : crée l'administrateur de l'instance et fixe les réglages de base.
 * Possible une seule fois (tant qu'aucun administrateur n'existe) et seulement avec le code d'installation affiché dans les journaux du serveur.
 */
final class SetupController extends AbstractController
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InstanceConfig $config,
        private readonly SetupToken $token,
        private readonly TokenService $tokens,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly CacheItemPoolInterface $cache,
        private readonly \App\Instance\AuditLog $audit,
    ) {
    }

    #[Route('/api/setup', methods: ['POST'])]
    public function __invoke(Request $r): JsonResponse
    {
        if ($this->em->getRepository(User::class)->count(['instanceAdmin' => true]) > 0) {
            return new JsonResponse(['title' => 'Cette instance est déjà installée.'], Response::HTTP_CONFLICT);
        }
        $in = $r->toArray();

        // Anti force brute : cinq essais par adresse IP et par heure.
        $item = $this->cache->getItem('setup_attempts_'.sha1((string) $r->getClientIp()));
        $n = (int) $item->get();
        if ($n >= self::MAX_ATTEMPTS) {
            return new JsonResponse(['title' => 'Trop d’essais. Réessaie dans une heure.'], Response::HTTP_TOO_MANY_REQUESTS);
        }
        if (!$this->token->check((string) ($in['code'] ?? ''))) {
            $this->cache->save($item->set($n + 1)->expiresAfter(3600));

            return new JsonResponse(['title' => 'Ce code d’installation n’est pas le bon. Il est affiché dans les journaux du serveur.', 'field' => 'code'], Response::HTTP_FORBIDDEN);
        }

        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $name = trim((string) ($in['name'] ?? ''));
        $password = (string) ($in['password'] ?? '');
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            return $this->invalid('email', 'Cette adresse n’a pas l’air valide.');
        }
        if ($name === '' || mb_strlen($name) > 160) {
            return $this->invalid('name', 'Indique ton nom.');
        }
        if (mb_strlen($password) < 10 || mb_strlen($password) > 200 || mb_strtolower($password) === $email) {
            return $this->invalid('password', 'Le mot de passe doit faire au moins 10 caractères et différer de ton adresse.');
        }
        $errors = $this->config->apply(array_intersect_key($in['config'] ?? [], array_flip(['instanceName', 'registration', 'supportEmail'])) + ['setupDone' => true]);
        if ($errors !== []) {
            return new JsonResponse(['title' => 'Certains réglages ne sont pas valides.', 'fields' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Un compte existant à cette adresse (ex. mise à niveau) devient administrateur ; sinon on le crée.
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]) ?? $this->em->getRepository(User::class)->findOneBy(['username' => $email]);
        $user ??= new User($email, $name, $email);
        $user->setInstanceAdmin(true)->setDisplayName($name)->setPasswordHash($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $this->token->discard();
        $this->audit->record($user, 'setup.done');

        return new JsonResponse(['token' => $this->tokens->issue($user, 'setup')], Response::HTTP_CREATED);
    }

    private function invalid(string $field, string $title): JsonResponse
    {
        return new JsonResponse(['title' => $title, 'field' => $field], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
