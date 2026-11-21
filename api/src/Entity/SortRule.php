<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Règle de tri déterministe (F-15), sans IA : un expéditeur, un domaine ou un mot de l'objet range le mail reçu dans les archives
 * ou à la corbeille (jamais supprimé). Visible, désactivable, supprimable. Un mail qui a un suivi n'est jamais rangé.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'sort_rule_unique', columns: ['owner_id', 'field', 'rule_value'])]
class SortRule
{
    public const FIELDS = ['sender', 'domain', 'subject'];
    public const ACTIONS = ['archive', 'trash'];

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;
    #[ORM\Column]
    private bool $enabled = true;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $owner,
        #[ORM\Column(length: 10)] private string $field,
        #[ORM\Column(name: 'rule_value', length: 180)] private string $value,
        #[ORM\Column(length: 10)] private string $action,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->value = mb_strtolower(trim($value));
    }

    public function getId(): string { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getAction(): string { return $this->action; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $v): static { $this->enabled = $v; return $this; }

    public function matches(string $fromAddress, string $subject): bool
    {
        $a = mb_strtolower(trim($fromAddress));

        return match ($this->field) {
            'sender' => $a === $this->value,
            'domain' => str_ends_with($a, '@'.ltrim($this->value, '@')),
            default => str_contains(mb_strtolower($subject), $this->value),
        };
    }

    /** @return array{id: string, field: string, value: string, action: string, enabled: bool} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'field' => $this->field, 'value' => $this->value, 'action' => $this->action, 'enabled' => $this->enabled];
    }
}
