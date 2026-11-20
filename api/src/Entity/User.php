<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        /** Identifiant CAS (uid) : unique, stable. */
        #[ORM\Column(length: 120, unique: true)] private string $username,
        #[ORM\Column(length: 160)] private string $displayName = '',
        #[ORM\Column(length: 180, nullable: true)] private ?string $email = null,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->displayName = $displayName !== '' ? $displayName : $username;
    }

    public function getId(): string { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $v): static { $this->displayName = $v; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): static { $this->email = $v; return $this; }
    public function getUserIdentifier(): string { return $this->username; }
    public function getRoles(): array { return ['ROLE_USER']; }
}
