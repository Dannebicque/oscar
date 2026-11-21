<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Jeton d'accès opaque, révocable (N-05 : sessions révocables). Seul son hachage est stocké. */
#[ORM\Entity]
class ApiToken
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 64, unique: true)] private string $tokenHash,
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $user,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $expiresAt,
        #[ORM\Column(length: 80)] private string $label = 'web',
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function getId(): string { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getTokenHash(): string { return $this->tokenHash; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function isExpired(?\DateTimeImmutable $now = null): bool { return $this->expiresAt <= ($now ?? new \DateTimeImmutable()); }
}
