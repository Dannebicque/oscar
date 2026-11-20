<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Membre d'une boîte partagée : « member » lit, prend, commente ; « admin » gère les membres (§2). */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'shared_member', columns: ['mailbox_id', 'user_id'])]
class SharedMailboxMember
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'members')] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private SharedMailbox $mailbox,
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $user,
        #[ORM\Column(length: 10)] private string $role = 'member',
    ) {
        $this->id = Uuid::v7()->toRfc4122();
    }

    public function getMailbox(): SharedMailbox { return $this->mailbox; }
    public function getUser(): User { return $this->user; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $r): static { $this->role = $r; return $this; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
}
