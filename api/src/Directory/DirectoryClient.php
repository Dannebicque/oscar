<?php

namespace App\Directory;

/** Accès à l'annuaire de l'établissement (LDAP). Un seul point d'entrée, remplaçable dans les tests. */
interface DirectoryClient
{
    public function isConfigured(): bool;

    /**
     * @param list<string> $attributes
     *
     * @return list<array<string, string>> entrées (attribut => première valeur), au plus $limit
     *
     * @throws \RuntimeException si l'annuaire est injoignable
     */
    public function search(string $filter, array $attributes, int $limit): array;
}
