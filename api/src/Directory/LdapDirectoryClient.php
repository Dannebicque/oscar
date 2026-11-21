<?php

namespace App\Directory;

/** Client LDAP natif (extension ldap). Lecture seule, avec un compte de service ; délai court ; aucune écriture. */
final class LdapDirectoryClient implements DirectoryClient
{
    public function __construct(
        private readonly string $url,
        private readonly string $baseDn,
        private readonly string $bindDn,
        #[\SensitiveParameter] private readonly string $bindPassword,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->url !== '' && $this->baseDn !== '' && \function_exists('ldap_connect');
    }

    public function search(string $filter, array $attributes, int $limit): array
    {
        $link = @ldap_connect($this->url);
        if ($link === false) {
            throw new \RuntimeException('Annuaire injoignable.');
        }
        ldap_set_option($link, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($link, LDAP_OPT_NETWORK_TIMEOUT, 4);
        ldap_set_option($link, LDAP_OPT_TIMELIMIT, 4);
        ldap_set_option($link, LDAP_OPT_REFERRALS, 0);
        try {
            if (!@($this->bindDn !== '' ? ldap_bind($link, $this->bindDn, $this->bindPassword) : ldap_bind($link))) {
                throw new \RuntimeException('Annuaire : connexion refusée.');
            }
            $res = @ldap_search($link, $this->baseDn, $filter, $attributes, 0, $limit, 4);
            if ($res === false) {
                throw new \RuntimeException('Annuaire : recherche impossible.');
            }
            $out = [];
            foreach (ldap_get_entries($link, $res) as $entry) {
                if (!\is_array($entry)) {
                    continue;
                }
                $row = [];
                foreach ($attributes as $a) {
                    $v = $entry[strtolower($a)][0] ?? null;
                    if (\is_string($v) && $v !== '') {
                        $row[$a] = $v;
                    }
                }
                if ($row !== []) {
                    $out[] = $row;
                }
            }

            return \array_slice($out, 0, $limit);
        } finally {
            @ldap_unbind($link);
        }
    }
}
