<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;

/** Journal des événements et des erreurs (F-78), distinct du journal d'un suivi. Jamais de contenu de mail. */
#[ORM\Entity]
#[ORM\Index(columns: ['owner_id', 'at'])]
#[ApiResource(operations: [new GetCollection(uriTemplate: '/log')], formats: ['json' => ['application/json']], order: ['at' => 'DESC'], paginationEnabled: false)]
class LogEntry implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $at;

    public function __construct(
        #[ORM\Column(length: 160)] private string $account,
        /** auth | sync | send | calendar */
        #[ORM\Column(length: 30)] private string $type,
        #[ORM\Column(type: Types::TEXT)] private string $message,
        /** Action proposée : reconnect | retry | none (traduite côté front). */
        #[ORM\Column(length: 30)] private string $action = 'none',
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->at = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    #[Ignore] public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $o): static { $this->owner = $o; return $this; }
    public function getAt(): \DateTimeImmutable { return $this->at; }
    public function getAccount(): string { return $this->account; }
    public function getType(): string { return $this->type; }
    public function getMessage(): string { return $this->message; }
    public function getAction(): string { return $this->action; }
}
