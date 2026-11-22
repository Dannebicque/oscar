<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Journal d'audit de l'instance : qui a changé quoi dans l'administration (réglages, comptes, droits).
 * Jamais de contenu d'utilisateur ni de secret : seulement des actions et des noms de réglages.
 * L'auteur est recopié (nom) pour que l'historique survive à la suppression de son compte.
 */
#[ORM\Entity]
#[ORM\Index(columns: ['at'])]
class AuditEntry
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $at;

    /** @param array<string, scalar|list<string>> $details */
    public function __construct(
        #[ORM\Column(length: 160)] private string $actor,
        /** config.changed | user.invited | user.disabled | user.enabled | user.deleted | user.admin | user.resetLink | setup.done */
        #[ORM\Column(length: 40)] private string $action,
        #[ORM\Column(length: 180, nullable: true)] private ?string $target = null,
        #[ORM\Column(type: Types::JSON)] private array $details = [],
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->at = new \DateTimeImmutable();
    }

    /** @return array{id: string, at: string, actor: string, action: string, target: ?string, details: array<string, mixed>} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'at' => $this->at->format(\DATE_ATOM), 'actor' => $this->actor, 'action' => $this->action, 'target' => $this->target, 'details' => $this->details];
    }
}
