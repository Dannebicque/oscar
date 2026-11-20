<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\SuiviState;
use App\Repository\SuiviRepository;
use App\State\CloseSuiviProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un suivi par fil de conversation. Jamais de clôture automatique :
 * `close` est une action explicite de l'utilisateur.
 */
#[ORM\Entity(repositoryClass: SuiviRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            uriTemplate: '/suivis/{id}/close',
            status: 204,
            read: true,
            deserialize: false,
            validate: false,
            output: false,
            processor: CloseSuiviProcessor::class,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Clôture un suivi (action explicite).'),
        ),
    ],
    formats: ['json' => ['application/json']],
    order: ['dueAt' => 'ASC'],
)]
class Suivi
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $subject = '';

    #[ORM\Column(length: 255)]
    private string $contact = '';

    #[ORM\Column(length: 20, enumType: SuiviState::class)]
    private SuiviState $state = SuiviState::Waiting;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueAt;

    #[ORM\Column]
    private bool $suggestedByAi = false;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->dueAt = new \DateTimeImmutable('today');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getContact(): string
    {
        return $this->contact;
    }

    public function setContact(string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getState(): SuiviState
    {
        return $this->state;
    }

    public function setState(SuiviState $state): static
    {
        $this->state = $state;

        return $this;
    }

    /** ISO date (AAAA-MM-JJ), comme attendu par le front. */
    public function getDueAt(): string
    {
        return $this->dueAt->format('Y-m-d');
    }

    #[Ignore]
    public function getDueAtDate(): \DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    public function isSuggestedByAi(): bool
    {
        return $this->suggestedByAi;
    }

    public function setSuggestedByAi(bool $suggestedByAi): static
    {
        $this->suggestedByAi = $suggestedByAi;

        return $this;
    }

    public function close(): static
    {
        $this->state = SuiviState::Done;

        return $this;
    }
}
