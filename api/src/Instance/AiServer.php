<?php

namespace App\Instance;

use App\Entity\InstanceSetting;
use App\Security\CredentialVault;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le modèle de langage de l'établissement (§5), réglé par l'administrateur : service, adresse, modèle, clé chiffrée.
 * Gardé hors des réglages publics de l'instance : la clé ne ressort jamais, l'adresse n'est montrée qu'aux personnes connectées.
 * Sans serveur réglé, le mode « IA de l'établissement » applique les règles locales.
 */
final class AiServer
{
    private const KEY = 'aiServer';

    public function __construct(private readonly EntityManagerInterface $em, private readonly CredentialVault $vault)
    {
    }

    /** @return ?array{kind: string, baseUrl: string, model: string, key: ?string} `key` : chiffrée */
    public function stored(): ?array
    {
        $v = $this->em->find(InstanceSetting::class, self::KEY)?->getValue();

        return \is_array($v) && isset($v['kind'], $v['baseUrl'], $v['model']) ? $v + ['key' => null] : null;
    }

    public function apiKey(): ?string
    {
        $key = $this->stored()['key'] ?? null;

        return $key === null ? null : $this->vault->decrypt($key);
    }

    /** Où partent les textes : montré à chaque utilisateur. */
    public function host(): ?string
    {
        $s = $this->stored();
        if ($s === null) {
            return null;
        }
        $p = parse_url($s['baseUrl']);

        return ($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '');
    }

    /** `$apiKey` : null = garder la clé (seulement si l'adresse reste sur le même serveur), '' = la retirer. */
    public function save(string $kind, string $baseUrl, string $model, ?string $apiKey): void
    {
        $before = $this->stored();
        $sameServer = $before !== null && $this->origin($before['baseUrl']) === $this->origin($baseUrl);
        // Une clé n'est jamais envoyée à un autre serveur que celui pour lequel elle a été saisie.
        $key = $apiKey === null ? ($sameServer ? $before['key'] : null) : ($apiKey === '' ? null : $this->vault->encrypt($apiKey));
        $value = ['kind' => $kind, 'baseUrl' => rtrim($baseUrl, '/'), 'model' => $model, 'key' => $key];
        $row = $this->em->find(InstanceSetting::class, self::KEY);
        if ($row === null) {
            $this->em->persist(new InstanceSetting(self::KEY, $value));
        } else {
            $row->setValue($value);
        }
        $this->em->flush();
    }

    public function clear(): void
    {
        $row = $this->em->find(InstanceSetting::class, self::KEY);
        if ($row !== null) {
            $this->em->remove($row);
            $this->em->flush();
        }
    }

    private function origin(string $url): string
    {
        $p = parse_url($url);

        return strtolower(($p['scheme'] ?? '').'://'.($p['host'] ?? '').':'.($p['port'] ?? ''));
    }
}
