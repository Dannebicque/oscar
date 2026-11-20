<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\CasClient;
use App\Security\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Connexion à l'application. CAS en production ; connexion de développement seulement en dev et test.
 * Le navigateur et l'application de bureau reçoivent un jeton opaque, échangé contre un code à usage unique.
 */
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TokenService $tokens,
        private readonly CasClient $cas,
        private readonly CacheItemPoolInterface $cache,
        #[Autowire('%kernel.environment%')] private readonly string $env,
        #[Autowire('%env(CORS_ALLOW_ORIGIN)%')] private readonly string $frontOriginRegex,
    ) {
    }

    #[Route('/api/auth/dev-login', methods: ['POST'])]
    public function devLogin(Request $r): JsonResponse
    {
        if (!\in_array($this->env, ['dev', 'test'], true)) {
            return new JsonResponse(['title' => 'Indisponible'], Response::HTTP_NOT_FOUND);
        }
        $username = trim((string) ($r->toArray()['username'] ?? ''));
        if ($username === '' || !preg_match('/^[a-z0-9._-]{1,60}$/i', $username)) {
            return new JsonResponse(['title' => 'Identifiant invalide'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['token' => $this->tokens->issue($this->userFor($username, ucfirst($username), null), 'dev')]);
    }

    #[Route('/api/auth/cas/login', methods: ['GET'])]
    public function casLogin(Request $r): Response
    {
        $return = (string) $r->query->get('return', '');
        if (!$this->allowedReturn($return)) {
            return new JsonResponse(['title' => 'Adresse de retour non autorisée'], Response::HTTP_BAD_REQUEST);
        }
        $service = $this->generateUrl('app_auth_cas_callback', ['return' => $return], 0);

        return new RedirectResponse($this->cas->loginUrl($r->getSchemeAndHttpHost().$service));
    }

    #[Route('/api/auth/cas/callback', name: 'app_auth_cas_callback', methods: ['GET'])]
    public function casCallback(Request $r): Response
    {
        $return = (string) $r->query->get('return', '');
        $ticket = (string) $r->query->get('ticket', '');
        if (!$this->allowedReturn($return) || $ticket === '') {
            return new JsonResponse(['title' => 'Requête invalide'], Response::HTTP_BAD_REQUEST);
        }
        $service = $r->getSchemeAndHttpHost().$this->generateUrl('app_auth_cas_callback', ['return' => $return], 0);
        $identity = $this->cas->validate($ticket, $service);
        if ($identity === null) {
            return new RedirectResponse($return.'#error=cas');
        }
        $user = $this->userFor($identity['uid'], $identity['name'], $identity['email']);
        $code = bin2hex(random_bytes(24));
        $item = $this->cache->getItem('authcode_'.$code)->set($user->getId())->expiresAfter(60);
        $this->cache->save($item);

        return new RedirectResponse($return.'#code='.$code);
    }

    /** Échange le code à usage unique contre un jeton (le code ne sert qu'une fois). */
    #[Route('/api/auth/token', methods: ['POST'])]
    public function exchange(Request $r): JsonResponse
    {
        $code = (string) ($r->toArray()['code'] ?? '');
        $item = $this->cache->getItem('authcode_'.preg_replace('/[^a-f0-9]/', '', $code));
        if (!$item->isHit()) {
            return new JsonResponse(['title' => 'Code expiré, reconnecte-toi.'], Response::HTTP_UNAUTHORIZED);
        }
        $this->cache->deleteItem($item->getKey());
        $user = $this->em->find(User::class, $item->get());

        return $user === null ? new JsonResponse(['title' => 'Utilisateur inconnu'], Response::HTTP_UNAUTHORIZED) : new JsonResponse(['token' => $this->tokens->issue($user, 'cas')]);
    }

    #[Route('/api/me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(['id' => $user->getId(), 'username' => $user->getUsername(), 'displayName' => $user->getDisplayName(), 'email' => $user->getEmail()]);
    }

    #[Route('/api/auth/logout', methods: ['POST'])]
    public function logout(Request $r): JsonResponse
    {
        $h = (string) $r->headers->get('Authorization', '');
        if (str_starts_with($h, 'Bearer ')) {
            $this->tokens->revoke(substr($h, 7));
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function userFor(string $username, string $name, ?string $email): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($user === null) {
            $user = new User($username, $name, $email);
            $this->em->persist($user);
            $this->em->flush();
        }

        return $user;
    }

    /** Anti-redirection ouverte : le retour doit correspondre à l'origine autorisée du front. */
    private function allowedReturn(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return (bool) preg_match('#'.str_replace('#', '\#', trim($this->frontOriginRegex, "'")).'#', $origin);
    }
}
