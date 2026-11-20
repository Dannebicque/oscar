<?php

namespace App\Ai;

/** Choisit le fournisseur selon le mode de l'utilisateur : l'IA de l'université par défaut, coupée ou externe (désactivée par défaut). */
final class AiProviderRegistry
{
    public function __construct(private readonly AiProvider $university)
    {
    }

    /** @return ?AiProvider null si l'IA est coupée ou si le fournisseur externe n'est pas configuré */
    public function for(string $mode): ?AiProvider
    {
        return match ($mode) {
            'university' => $this->university,
            default => null, // 'off' ; 'external' : aucun fournisseur externe configuré dans cette instance
        };
    }
}
