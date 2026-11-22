<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Comptes locaux : adresse et mot de passe (le CAS reste la voie de l'établissement). Inscription, connexion, mot de passe oublié.
 * Règles : mot de passe d'au moins 10 caractères, haché ; message de connexion identique que le compte existe ou non ;
 * essais limités ; lien de réinitialisation à usage unique valable une heure, jamais divulgué en réponse (sauf en développement).
 */
final class PasswordAuthController extends AbstractController
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TokenService $tokens,
        private readonly CacheItemPoolInterface $cache,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.environment%')] private readonly string $env,
        private readonly \App\Instance\InstanceConfig $instance,
        private readonly string $frontUrl,
        private readonly string $mailerFrom,
        private readonly string $casBaseUrl,
    ) {
    }

    /** Ce que la page de connexion doit proposer. */
    #[Route('/api/auth/config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        return new JsonResponse([
            'password' => true,
            'registration' => $this->instance->registrationOpen(), 'name' => $this->instance->name(),
            'cas' => trim($this->casBaseUrl) !== '' && !str_contains($this->casBaseUrl, 'example.org'),
            'dev' => \in_array($this->env, ['dev', 'test'], true),
        ]);
    }

    #[Route('/api/auth/register', methods: ['POST'])]
    public function register(Request $r): JsonResponse
    {
        if (!$this->instance->registrationOpen()) {
            return new JsonResponse(['title' => 'Les inscriptions sont fermées. Connecte-toi avec le compte de ton établissement.'], Response::HTTP_FORBIDDEN);
        }
        $in = $r->toArray();
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $name = trim((string) ($in['name'] ?? ''));
        $password = (string) ($in['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            return $this->invalid('email', 'Cette adresse n’a pas l’air valide.');
        }
        if ($name === '' || mb_strlen($name) > 160) {
            return $this->invalid('name', 'Indique ton nom, il apparaîtra dans tes messages.');
        }
        if ($bad = $this->passwordProblem($password, $email)) {
            return $this->invalid('password', $bad);
        }
        if ($this->em->getRepository(User::class)->findOneBy(['username' => $email]) !== null || $this->em->getRepository(User::class)->findOneBy(['email' => $email]) !== null) {
            return new JsonResponse(['title' => 'Cette adresse a déjà un compte. Connecte-toi, ou réinitialise ton mot de passe.', 'field' => 'email'], Response::HTTP_CONFLICT);
        }
        $user = new User($email, $name, $email);
        $user->setPasswordHash($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse(['token' => $this->tokens->issue($user, 'password')], Response::HTTP_CREATED);
    }

    #[Route('/api/auth/login', methods: ['POST'])]
    public function login(Request $r): JsonResponse
    {
        $in = $r->toArray();
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');
        $key = 'loginfail_'.sha1($email.'|'.$r->getClientIp());
        $item = $this->cache->getItem($key);
        if ((int) $item->get() >= self::MAX_ATTEMPTS) {
            return new JsonResponse(['title' => 'Trop d’essais. Patiente un quart d’heure ou réinitialise ton mot de passe.'], Response::HTTP_TOO_MANY_REQUESTS);
        }
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        // Même durée de calcul, que le compte existe ou non : on ne révèle pas quelles adresses ont un compte.
        $ok = $user?->getPassword() !== null ? $this->hasher->isPasswordValid($user, $password) : $this->burn($password);
        if (!$ok || $user === null) {
            $this->cache->save($item->set((int) $item->get() + 1)->expiresAfter(900));

            return new JsonResponse(['title' => 'Adresse ou mot de passe incorrect.'], Response::HTTP_UNAUTHORIZED);
        }
        $this->cache->deleteItem($key);

        return new JsonResponse(['token' => $this->tokens->issue($user, 'password')]);
    }

    /** Toujours 204 : la réponse ne dit pas si l'adresse a un compte. */
    #[Route('/api/auth/forgot', methods: ['POST'])]
    public function forgot(Request $r): JsonResponse
    {
        $email = mb_strtolower(trim((string) ($r->toArray()['email'] ?? '')));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->em->getRepository(User::class)->findOneBy(['email' => $email]) : null;
        $link = null;
        if ($user !== null && $user->getPassword() !== null) {
            $token = bin2hex(random_bytes(32));
            $this->cache->save($this->cache->getItem('pwdreset_'.hash('sha256', $token))->set($user->getId())->expiresAfter(3600));
            $link = rtrim($this->frontUrl, '/').'/mot-de-passe/reinitialiser#token='.$token;
            try {
                $this->mailer->send((new Email())->from($this->mailerFrom)->to($email)->subject('Réinitialiser ton mot de passe Oscar')
                    ->text("Bonjour,\n\nTu as demandé à réinitialiser ton mot de passe. Ce lien est valable une heure et ne sert qu'une fois :\n\n$link\n\nSi ce n'est pas toi, ignore ce message : rien ne change.\n\nOscar"));
            } catch (\Throwable $e) {
                $this->logger->warning('Courriel de réinitialisation non envoyé : '.$e->getMessage());
            }
        }

        // En développement seulement, le lien est renvoyé pour pouvoir essayer sans serveur de courriel.
        return new JsonResponse($this->env !== 'prod' && $link !== null ? ['devLink' => $link] : null, $this->env !== 'prod' && $link !== null ? Response::HTTP_OK : Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/auth/reset', methods: ['POST'])]
    public function reset(Request $r): JsonResponse
    {
        $in = $r->toArray();
        $item = $this->cache->getItem('pwdreset_'.hash('sha256', (string) ($in['token'] ?? '')));
        $user = $item->isHit() ? $this->em->find(User::class, (string) $item->get()) : null;
        if ($user === null) {
            return new JsonResponse(['title' => 'Ce lien a expiré ou a déjà servi. Demande-en un nouveau.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($bad = $this->passwordProblem((string) ($in['password'] ?? ''), (string) $user->getEmail())) {
            return $this->invalid('password', $bad);
        }
        $user->setPasswordHash($this->hasher->hashPassword($user, (string) $in['password']));
        $this->em->flush();
        $this->cache->deleteItem($item->getKey());
        $this->tokens->revokeAll($user); // déconnecté partout

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function passwordProblem(string $password, string $email): ?string
    {
        if (mb_strlen($password) < 10) {
            return 'Ton mot de passe doit faire au moins 10 caractères.';
        }
        if (mb_strlen($password) > 200 || mb_strtolower($password) === $email) {
            return 'Choisis un mot de passe différent de ton adresse.';
        }

        return null;
    }

    private function burn(string $password): bool
    {
        $this->hasher->hashPassword(new User('x'), $password);

        return false;
    }

    private function invalid(string $field, string $message): JsonResponse
    {
        return new JsonResponse(['title' => $message, 'field' => $field], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
