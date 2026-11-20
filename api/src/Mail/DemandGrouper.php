<?php

namespace App\Mail;

/**
 * Un même message reçu dans plusieurs boîtes (alias) est reconnu par son identifiant
 * et devient une seule demande (F-44).
 */
final class DemandGrouper
{
    /**
     * @param iterable<MailHeader> $headers
     *
     * @return array<string, list<MailHeader>> exemplaires, indexés par Message-ID normalisé
     */
    public function group(iterable $headers): array
    {
        $groups = [];
        foreach ($headers as $h) {
            $groups[$h->key()][] = $h;
        }
        ksort($groups);

        return $groups;
    }
}
