<?php

namespace App\Ai;

use App\Entity\AiProviderConfig;
use App\Entity\User;
use App\Security\CredentialVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Choisit le fournisseur selon le mode de l'utilisateur : l'IA de l'établissement par défaut (son modèle s'il est réglé, sinon les règles
 * locales), coupée, ou externe (désactivée par défaut, à configurer).
 */
final class AiProviderRegistry
{
    public function __construct(
        private readonly AiProvider $university,
        private readonly EntityManagerInterface $em,
        private readonly CredentialVault $vault,
        private readonly HttpClientInterface $http,
        private readonly \App\Instance\InstanceConfig $instance,
        private readonly \App\Instance\AiServer $server,
    ) {
    }

    /** @return ?AiProvider null si l'IA est coupée ou si le fournisseur externe n'est pas (encore) configuré */
    public function for(string $mode, ?User $user = null): ?AiProvider
    {
        return match ($mode) {
            'university' => $this->institution() ?? $this->university,
            // L'administrateur peut interdire l'IA externe (données sensibles) : on retombe alors sur l'IA de l'établissement.
            'external' => $user === null ? null : ($this->instance->aiExternalAllowed() ? $this->external($user) : ($this->institution() ?? $this->university)),
            default => null,
        };
    }

    /** Le modèle réglé par l'administrateur, avec repli sur les règles locales ; null s'il n'y en a pas. */
    public function institution(): ?ExternalAiProvider
    {
        $s = $this->server->stored();

        return $s === null ? null : new ExternalAiProvider($this->http, $this->university, $s['kind'], $s['baseUrl'], $s['model'], $this->server->apiKey(), 'institution');
    }

    public function external(User $user): ?ExternalAiProvider
    {
        $c = $this->em->getRepository(AiProviderConfig::class)->findOneBy(['owner' => $user]);
        if ($c === null || !$c->isComplete()) {
            return null;
        }
        $key = $c->hasKey() ? $this->vault->decrypt((string) $c->getApiKeyEncrypted()) : null;

        return new ExternalAiProvider($this->http, $this->university, $c->getKind(), $c->getBaseUrl(), $c->getModel(), $key);
    }
}
