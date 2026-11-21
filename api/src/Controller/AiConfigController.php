<?php

namespace App\Controller;

use App\Ai\AiProviderRegistry;
use App\Entity\AiProviderConfig;
use App\Entity\User;
use App\Security\CredentialVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Fournisseur d'IA externe (§5) : l'utilisateur choisit le service, le modèle et sa clé. La clé est chiffrée hors base, jamais renvoyée.
 * En production, l'adresse doit être en HTTPS et publique (pas de réseau interne) : l'utilisateur ne peut pas faire appeler une machine du réseau.
 */
final class AiConfigController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CredentialVault $vault,
        private readonly AiProviderRegistry $registry,
        #[Autowire('%kernel.environment%')] private readonly string $env,
    ) {
    }

    #[Route('/api/ai-config', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        $c = $this->em->getRepository(AiProviderConfig::class)->findOneBy(['owner' => $user]);

        return new JsonResponse($c === null
            ? ['kind' => 'openai', 'baseUrl' => '', 'model' => '', 'hasKey' => false, 'configured' => false]
            : ['kind' => $c->getKind(), 'baseUrl' => $c->getBaseUrl(), 'model' => $c->getModel(), 'hasKey' => $c->hasKey(), 'configured' => $c->isComplete()]);
    }

    /** `apiKey` : absent = on garde la clé actuelle ; chaîne vide = on la retire ; texte = nouvelle clé. */
    #[Route('/api/ai-config', methods: ['PUT'])]
    public function update(Request $r, #[CurrentUser] User $user): JsonResponse
    {
        $in = $r->toArray();
        $kind = (string) ($in['kind'] ?? '');
        $url = trim((string) ($in['baseUrl'] ?? ''));
        $model = trim((string) ($in['model'] ?? ''));
        if (!\in_array($kind, AiProviderConfig::KINDS, true)) {
            return $this->invalid('kind', 'Choisis un type de service.');
        }
        if ($bad = $this->urlProblem($url)) {
            return $this->invalid('baseUrl', $bad);
        }
        if ($model === '' || mb_strlen($model) > 120 || !preg_match('/^[\w.:\/\- ]+$/u', $model)) {
            return $this->invalid('model', 'Indique le nom du modèle (par exemple gpt-4o-mini).');
        }
        $key = \array_key_exists('apiKey', $in) ? (string) $in['apiKey'] : null;
        if ($key !== null && mb_strlen($key) > 500) {
            return $this->invalid('apiKey', 'Cette clé est trop longue.');
        }
        $c = $this->em->getRepository(AiProviderConfig::class)->findOneBy(['owner' => $user]) ?? new AiProviderConfig($user);
        $c->configure($kind, $url, $model);
        if ($key !== null) {
            $c->setApiKeyEncrypted($key === '' ? null : $this->vault->encrypt($key));
        }
        $this->em->persist($c);
        $this->em->flush();

        return $this->show($user);
    }

    /** Un échange minimal : dit si l'adresse, le modèle et la clé fonctionnent, sans envoyer aucun mail. */
    #[Route('/api/ai-config/test', methods: ['POST'])]
    public function test(#[CurrentUser] User $user): JsonResponse
    {
        $p = $this->registry->external($user);
        if ($p === null) {
            return new JsonResponse(['ok' => false, 'message' => 'Renseigne d’abord l’adresse et le modèle, puis enregistre.']);
        }
        try {
            $p->ping();
        } catch (\Throwable $e) {
            $code = $e instanceof \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface ? $e->getResponse()->getStatusCode() : 0;
            $why = match (true) {
                \in_array($code, [401, 403], true) => 'La clé est refusée. Vérifie-la.',
                $code === 404 => 'Adresse ou modèle introuvable. Vérifie l’adresse du service et le nom du modèle.',
                $code === 429 => 'Le service limite les appels. Réessaie dans un instant.',
                default => 'Je n’arrive pas à joindre le service. Vérifie l’adresse et ta connexion.',
            };

            return new JsonResponse(['ok' => false, 'message' => $why]);
        }

        return new JsonResponse(['ok' => true, 'message' => 'Le service répond. Oscar pourra l’utiliser.']);
    }

    private function urlProblem(string $url): ?string
    {
        $p = parse_url($url);
        if ($p === false || !isset($p['scheme'], $p['host']) || !\in_array($p['scheme'], ['https', 'http'], true) || isset($p['user']) || isset($p['query']) || isset($p['fragment'])) {
            return 'Indique une adresse complète, par exemple https://api.openai.com/v1.';
        }
        if (\in_array($this->env, ['dev', 'test'], true)) {
            return null;
        }
        if ($p['scheme'] !== 'https') {
            return 'L’adresse doit commencer par https:// : une clé ne voyage pas en clair.';
        }

        return $this->isInternal($p['host']) ? 'Cette adresse pointe vers un réseau interne ou introuvable : elle n’est pas autorisée.' : null;
    }

    private function isInternal(string $host): bool
    {
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return true;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }

    private function invalid(string $field, string $message): JsonResponse
    {
        return new JsonResponse(['title' => $message, 'field' => $field], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
