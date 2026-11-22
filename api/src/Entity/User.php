<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Mot de passe haché (comptes locaux). Null pour un compte CAS : on ne se connecte alors que par le CAS. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordHash = null;

    /** Administrateur de l'instance : règle la configuration globale. Il ne lit jamais les mails ni les agendas des utilisateurs. */
    #[ORM\Column(options: ['default' => false])]
    private bool $instanceAdmin = false;

    /** Compte désactivé par un administrateur : plus de connexion ni de synchronisation ; rien n'est supprimé. */
    #[ORM\Column(options: ['default' => false])]
    private bool $disabled = false;

    public function __construct(
        /** Identifiant CAS (uid) : unique, stable. */
        #[ORM\Column(length: 120, unique: true)] private string $username,
        #[ORM\Column(length: 160)] private string $displayName = '',
        #[ORM\Column(length: 180, nullable: true, unique: true)] private ?string $email = null,
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
    public function getPassword(): ?string { return $this->passwordHash; }
    public function setPasswordHash(?string $h): static { $this->passwordHash = $h; return $this; }
    public function isDisabled(): bool { return $this->disabled; }
    public function setDisabled(bool $v): static { $this->disabled = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isInstanceAdmin(): bool { return $this->instanceAdmin; }
    public function setInstanceAdmin(bool $v): static { $this->instanceAdmin = $v; return $this; }
    public function getRoles(): array { return $this->instanceAdmin ? ['ROLE_USER', 'ROLE_ADMIN'] : ['ROLE_USER']; }
}
