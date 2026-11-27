<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;

/** Journal consultable de chaque appel IA et de ce qu'il a produit (§5). L'entrée est résumée : jamais le contenu du mail. */
#[ORM\Entity]
#[ORM\Index(columns: ['owner_id', 'at'])]
#[ApiResource(operations: [new GetCollection(uriTemplate: '/ai-calls')], formats: ['json' => ['application/json']], order: ['at' => 'DESC'], paginationEnabled: false)]
class AiCall implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $at;

    /** @param array<string, mixed> $output */
    public function __construct(
        #[ORM\Column(length: 60)] private string $provider,
        #[ORM\Column(length: 40)] private string $purpose,
        #[ORM\Column(length: 300)] private string $inputSummary,
        #[ORM\Column(type: Types::JSON)] private array $output,
        /** Un modèle de langage a-t-il réellement répondu ? null : appel plus ancien, inconnu. */
        #[ORM\Column(nullable: true)] private ?bool $byModel = null,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->at = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    #[Ignore] public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $o): static { $this->owner = $o; return $this; }
    public function getAt(): \DateTimeImmutable { return $this->at; }
    public function getProvider(): string { return $this->provider; }
    public function getPurpose(): string { return $this->purpose; }
    public function getInputSummary(): string { return $this->inputSummary; }
    /** @return array<string, mixed> */
    public function getOutput(): array { return $this->output; }
    public function getByModel(): ?bool { return $this->byModel; }
}
