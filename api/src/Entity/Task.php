<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\TaskOrigin;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** Tâche de la to-do globale (F-30, F-31) : garde toujours un lien vers sa source. */
#[ORM\Entity]
#[ApiResource(
    operations: [new GetCollection(), new Get(), new Post(), new Patch(inputFormats: ['json' => ['application/merge-patch+json', 'application/json']]), new Delete()],
    formats: ['json' => ['application/json']],
    order: ['dueAt' => 'ASC'],
)]
class Task implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 300)] #[Assert\NotBlank] #[Assert\Length(max: 300)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $dueAt = null;

    /** 1 = urgente … 4 = basse. Manuelle : l'IA peut la suggérer, jamais la fixer (§4.4). */
    #[ORM\Column] #[Assert\Range(min: 1, max: 4)]
    private int $priority = 3;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $project = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)] #[Assert\All([new Assert\Length(max: 40)])] #[Assert\Count(max: 20)]
    private array $tags = [];

    #[ORM\Column]
    private bool $done = false;

    #[ORM\Column(length: 10, enumType: TaskOrigin::class)]
    private TaskOrigin $origin = TaskOrigin::Manual;

    /** Proposée par l'IA, pas encore confirmée (F-35). */
    #[ORM\Column]
    private bool $toConfirm = false;

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $source = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
    }

    public function getId(): string { return $this->id; }
    #[Ignore] public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $o): static { $this->owner = $o; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $v): static { $this->description = $v; return $this; }
    public function getDueAt(): ?\DateTimeImmutable { return $this->dueAt; }
    public function setDueAt(?\DateTimeImmutable $v): static { $this->dueAt = $v; return $this; }
    public function getPriority(): int { return $this->priority; }
    public function setPriority(int $v): static { $this->priority = $v; return $this; }
    public function getProject(): ?string { return $this->project; }
    public function setProject(?string $v): static { $this->project = $v; return $this; }
    /** @return list<string> */
    public function getTags(): array { return $this->tags; }
    public function setTags(array $v): static { $this->tags = array_values(array_unique(array_map('strval', $v))); return $this; }
    public function isDone(): bool { return $this->done; }
    public function setDone(bool $v): static { $this->done = $v; return $this; }
    public function getOrigin(): TaskOrigin { return $this->origin; }
    public function setOrigin(TaskOrigin $v): static { $this->origin = $v; return $this; }
    public function isToConfirm(): bool { return $this->toConfirm; }
    public function setToConfirm(bool $v): static { $this->toConfirm = $v; return $this; }
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $v): static { $this->source = $v; return $this; }
}
