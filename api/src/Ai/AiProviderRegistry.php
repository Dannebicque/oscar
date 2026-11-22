<?php

namespace App\Ai;

use App\Entity\AiProviderConfig;
use App\Entity\User;
use App\Security\CredentialVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Choisit le fournisseur selon le mode de l'utilisateur : l'IA de l'université par défaut, coupée, ou externe (désactivée par défaut, à configurer). */
final class AiProviderRegistry
{
    public function __construct(
        private readonly AiProvider $university,
        private readonly EntityManagerInterface $em,
        private readonly CredentialVault $vault,
        private readonly HttpClientInterface $http,
        private readonly \App\Instance\InstanceConfig $instance,
    ) {
    }

    /** @return ?AiProvider null si l'IA est coupée ou si le fournisseur externe n'est pas (encore) configuré */
    public function for(string $mode, ?User $user = null): ?AiProvider
    {
        return match ($mode) {
            'university' => $this->university,
            // L'administrateur peut interdire l'IA externe (données sensibles) : on retombe alors sur l'IA de l'établissement.
            'external' => $user === null ? null : ($this->instance->aiExternalAllowed() ? $this->external($user) : $this->university),
            default => null,
        };
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
