<?php

namespace App\Security;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class TokenService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly int $ttlSeconds = 12 * 3600)
    {
    }

    /** @return string jeton en clair, montré une seule fois */
    public function issue(User $user, string $label = 'web'): string
    {
        $plain = bin2hex(random_bytes(32));
        $this->em->persist(new ApiToken(ApiToken::hash($plain), $this->em->getReference(User::class, $user->getId()), new \DateTimeImmutable("+{$this->ttlSeconds} seconds"), $label));
        $this->em->flush();

        return $plain;
    }

    public function revoke(string $plain): void
    {
        $token = $this->em->getRepository(ApiToken::class)->findOneBy(['tokenHash' => ApiToken::hash($plain)]);
        if ($token !== null) {
            $this->em->remove($token);
            $this->em->flush();
        }
    }

    /** Toutes les sessions d'un utilisateur (changement de mot de passe : on déconnecte partout). */
    public function revokeAll(User $user): void
    {
        foreach ($this->em->getRepository(ApiToken::class)->findBy(['user' => $user]) as $t) {
            $this->em->remove($t);
        }
        $this->em->flush();
    }

    /** Toutes les sessions sauf celle qui vient de faire le changement (changement de mot de passe depuis Réglages). */
    public function revokeOthers(User $user, string $keepPlain): void
    {
        $keep = ApiToken::hash($keepPlain);
        foreach ($this->em->getRepository(ApiToken::class)->findBy(['user' => $user]) as $t) {
            if ($t->getTokenHash() !== $keep) {
                $this->em->remove($t);
            }
        }
        $this->em->flush();
    }
}
