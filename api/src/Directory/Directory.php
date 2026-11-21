<?php

namespace App\Directory;

/**
 * Annuaire global (F-18) : nom, prénom et e-mail au minimum, service et téléphone selon le choix de l'utilisateur.
 * Les attributs LDAP sont configurables (variables d'environnement) ; la saisie est TOUJOURS échappée (pas d'injection de filtre).
 */
final class Directory
{
    /**
     * @param array{first: string, last: string, mail: string, department: string, phone: string} $attrs noms d'attributs LDAP
     */
    public function __construct(private readonly DirectoryClient $client, private readonly array $attrs, private readonly string $extraFilter = '')
    {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /** @return list<array{name: string, address: string, detail: ?string}> */
    public function search(string $query, bool $showDepartment, bool $showPhone, int $limit = 8): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2 || !$this->isConfigured()) {
            return [];
        }
        $e = ldap_escape(mb_substr($q, 0, 60), '', LDAP_ESCAPE_FILTER);
        $a = $this->attrs;
        $filter = sprintf('(&(%s=*)(|(cn=*%s*)(%s=*%s*)(%s=*%s*)(%s=*%s*))%s)', $a['mail'], $e, $a['first'], $e, $a['last'], $e, $a['mail'], $e, $this->extraFilter);
        $attributes = array_values(array_unique(array_filter([$a['first'], $a['last'], $a['mail'], $showDepartment ? $a['department'] : null, $showPhone ? $a['phone'] : null])));
        $rows = $this->client->search($filter, $attributes, $limit);
        $out = [];
        foreach ($rows as $r) {
            $address = strtolower($r[$a['mail']] ?? '');
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $name = trim(($r[$a['first']] ?? '').' '.($r[$a['last']] ?? ''));
            $detail = implode(' · ', array_filter([$showDepartment ? ($r[$a['department']] ?? null) : null, $showPhone ? ($r[$a['phone']] ?? null) : null]));
            $out[] = ['name' => $name !== '' ? $name : $address, 'address' => $address, 'detail' => $detail !== '' ? $detail : null];
        }

        return $out;
    }
}
