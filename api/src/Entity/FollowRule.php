<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Règle de création automatique d'un suivi (F-66) : tout mail reçu d'un expéditeur ou d'un domaine donné devient une demande « à traiter ».
 * Visible, modifiable et désactivable ; elle s'ajoute aux suggestions de l'IA (qui, elles, se valident). Les boîtes partagées
 * ont leur propre règle (SharedMailbox::autoFollow).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'follow_rule_unique', columns: ['owner_id', 'kind', 'rule_value'])]
class FollowRule
{
    public const KINDS = ['sender', 'domain'];

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column]
    private bool $enabled = true;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $owner,
        #[ORM\Column(length: 10)] private string $kind,
        #[ORM\Column(name: 'rule_value', length: 180)] private string $value,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->value = mb_strtolower(trim($value));
    }

    public function getId(): string { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getKind(): string { return $this->kind; }
    public function getValue(): string { return $this->value; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $v): static { $this->enabled = $v; return $this; }

    public function matches(string $fromAddress): bool
    {
        $a = mb_strtolower(trim($fromAddress));

        return $this->kind === 'sender' ? $a === $this->value : str_ends_with($a, '@'.ltrim($this->value, '@'));
    }

    /** @return array{id: string, kind: string, value: string, enabled: bool} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'kind' => $this->kind, 'value' => $this->value, 'enabled' => $this->enabled];
    }
}
