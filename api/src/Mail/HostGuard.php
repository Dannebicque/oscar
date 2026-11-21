<?php

namespace App\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Anti-SSRF : le serveur se connecte à une adresse que l'utilisateur a saisie. En production, on refuse les adresses privées ou réservées
 * (réseau interne, boucle locale) et les ports qui ne sont pas ceux d'un serveur de messagerie. En dev et test, tout est permis (Dovecot local).
 */
final class HostGuard
{
    private const PORTS = [25, 110, 143, 465, 587, 993, 995, 2525];

    public function __construct(#[Autowire('%kernel.environment%')] private readonly string $env)
    {
    }

    /** @return ?string message à montrer à l'utilisateur, null si l'adresse est acceptable */
    public function refuse(string $host, int $port): ?string
    {
        if (\in_array($this->env, ['dev', 'test'], true)) {
            return null;
        }
        if (!\in_array($port, self::PORTS, true)) {
            return 'Ce port n’est pas celui d’un serveur de messagerie (143, 993, 587, 465…).';
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return 'Je ne trouve pas ce serveur. Vérifie l’adresse.';
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'Cette adresse pointe vers un réseau interne : elle n’est pas autorisée ici.';
            }
        }

        return null;
    }
}
