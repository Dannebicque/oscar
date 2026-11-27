<?php

namespace App\Controller;

use App\Ai\AiProviderRegistry;
use App\Entity\AiProviderConfig;
use App\Entity\User;
use App\Instance\AiServer;
use App\Instance\AuditLog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Modèle de langage de l'établissement, réservé à l'administrateur (/api/admin). Comme les mails des utilisateurs y partiront,
 * il faut confirmer qu'il est hébergé par l'établissement ; le changement est journalisé et l'adresse montrée à chacun.
 */
final class AdminAiServerController extends AbstractController
{
    public function __construct(
        private readonly AiServer $server,
        private readonly AuditLog $audit,
        private readonly AiProviderRegistry $registry,
        #[Autowire('%kernel.environment%')] private readonly string $env,
    ) {
    }

    #[Route('/api/admin/ai-server', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $s = $this->server->stored();

        return new JsonResponse($s === null
            ? ['configured' => false, 'kind' => 'openai', 'baseUrl' => '', 'model' => '', 'hasKey' => false]
            : ['configured' => true, 'kind' => $s['kind'], 'baseUrl' => $s['baseUrl'], 'model' => $s['model'], 'hasKey' => $s['key'] !== null]);
    }

    #[Route('/api/admin/ai-server', methods: ['PUT'])]
    public function update(Request $r, #[CurrentUser] User $admin): JsonResponse
    {
        $in = $r->toArray();
        $kind = (string) ($in['kind'] ?? '');
        $url = trim((string) ($in['baseUrl'] ?? ''));
        $model = trim((string) ($in['model'] ?? ''));
        $key = \array_key_exists('apiKey', $in) ? (string) $in['apiKey'] : null;
        if (!\in_array($kind, AiProviderConfig::KINDS, true)) {
            return $this->invalid('kind', 'Choisis un type de service.');
        }
        if ($bad = $this->urlProblem($url)) {
            return $this->invalid('baseUrl', $bad);
        }
        if ($model === '' || mb_strlen($model) > 120 || !preg_match('/^[\w.:\/\- ]+$/u', $model)) {
            return $this->invalid('model', 'Indique le nom du modèle.');
        }
        if ($key !== null && mb_strlen($key) > 500) {
            return $this->invalid('apiKey', 'Cette clé est trop longue.');
        }
        if (($in['hostedByUs'] ?? false) !== true) {
            return $this->invalid('hostedByUs', 'Confirme que ce serveur est hébergé par l’établissement : les mails des utilisateurs y seront lus.');
        }
        $this->server->save($kind, $url, $model, $key);
        // L'adresse du serveur (jamais la clé) : qui a décidé où partent les textes doit pouvoir se retrouver.
        $this->audit->record($admin, 'ai.server', $this->server->host(), []);

        return $this->show();
    }

    #[Route('/api/admin/ai-server', methods: ['DELETE'])]
    public function delete(#[CurrentUser] User $admin): JsonResponse
    {
        if ($this->server->stored() !== null) {
            $this->server->clear();
            $this->audit->record($admin, 'ai.serverRemoved', null, []);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Un échange minimal, sans aucun mail. */
    #[Route('/api/admin/ai-server/test', methods: ['POST'])]
    public function test(): JsonResponse
    {
        $p = $this->registry->institution();
        if ($p === null) {
            return new JsonResponse(['ok' => false, 'message' => 'Renseigne d’abord le serveur, puis enregistre.']);
        }
        try {
            $p->ping();
        } catch (\Throwable $e) {
            $code = $e instanceof \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface ? $e->getResponse()->getStatusCode() : 0;

            return new JsonResponse(['ok' => false, 'message' => match (true) {
                \in_array($code, [401, 403], true) => 'La clé est refusée. Vérifie-la.',
                $code === 404 => 'Adresse ou modèle introuvable.',
                default => 'Je n’arrive pas à joindre le serveur. Vérifie l’adresse et le réseau.',
            }]);
        }

        return new JsonResponse(['ok' => true, 'message' => 'Le serveur répond. Oscar l’utilise pour « IA de l’établissement ».']);
    }

    private function urlProblem(string $url): ?string
    {
        $p = parse_url($url);
        if ($p === false || !isset($p['scheme'], $p['host']) || !\in_array($p['scheme'], ['https', 'http'], true) || isset($p['user']) || isset($p['query']) || isset($p['fragment'])) {
            return 'Indique une adresse complète, par exemple https://ia.mon-etablissement.fr/v1.';
        }
        // En clair seulement vers le réseau interne : rien ne traverse Internet sans chiffrement.
        if ($p['scheme'] === 'http' && !\in_array($this->env, ['dev', 'test'], true)) {
            $ips = filter_var($p['host'], FILTER_VALIDATE_IP) ? [$p['host']] : (gethostbynamel($p['host']) ?: []);
            foreach ($ips ?: ['8.8.8.8'] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return 'Hors du réseau interne, l’adresse doit commencer par https://.';
                }
            }
        }

        return null;
    }

    private function invalid(string $field, string $message): JsonResponse
    {
        return new JsonResponse(['title' => $message, 'field' => $field], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
