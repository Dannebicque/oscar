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

    /** Invitation acceptée. Tant qu'elle ne l'est pas, rien n'est partagé : ni ses mails vers la boîte, ni les demandes de la boîte. */
    #[ORM\Column(options: ['default' => true])]
    private bool $accepted = true;

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
    public function isAccepted(): bool { return $this->accepted; }
    public function setAccepted(bool $v): static { $this->accepted = $v; return $this; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
}
