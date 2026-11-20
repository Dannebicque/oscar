<?php

namespace App\Security;

/**
 * Chiffre les identifiants de comptes (N-04, §3) : libsodium secretbox, clé HORS base de données
 * (variable d'environnement ou fichier monté), jamais dans le dépôt.
 */
final class CredentialVault
{
    private readonly string $key;

    public function __construct(#[\SensitiveParameter] string $base64Key)
    {
        $key = base64_decode(str_starts_with($base64Key, 'base64:') ? substr($base64Key, 7) : $base64Key, true);
        if ($key === false || \strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException('APP_VAULT_KEY doit contenir 32 octets encodés en base64 (php -r "echo base64_encode(random_bytes(32));").');
        }
        $this->key = $key;
    }

    public function encrypt(#[\SensitiveParameter] string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);
        if ($raw === false || \strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Identifiant chiffré illisible.');
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);
        if ($plain === false) {
            throw new \RuntimeException('Impossible de déchiffrer : la clé a changé ou la donnée est altérée.');
        }

        return $plain;
    }
}
