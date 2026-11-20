<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use App\Repository\SuiviRepository;
use App\State\CloseSuiviProcessor;
use App\State\SuiviProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un suivi par fil de conversation. Jamais de clôture ni de relance automatiques :
 * `close` et les changements d'état sont des actions explicites de l'utilisateur.
 */
#[ORM\Entity(repositoryClass: SuiviRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: SuiviProcessor::class),
        new Patch(inputFormats: ['json' => ['application/merge-patch+json', 'application/json']], processor: SuiviProcessor::class),
        new Post(
            uriTemplate: '/suivis/{id}/close',
            status: 204,
            read: true,
            deserialize: false,
            validate: false,
            output: false,
            processor: CloseSuiviProcessor::class,
            openapi: new Operation(summary: 'Clôture un suivi (action explicite).'),
        ),
    ],
    formats: ['json' => ['application/json']],
    order: ['dueAt' => 'ASC'],
)]
class Suivi implements OwnedInterface
{
    /** null pour une demande d'alias : elle appartient à la boîte partagée, pas à une personne (F-67). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?SharedMailbox $sharedMailbox = null;

    /** « Pris par X » : informe les autres membres pour éviter les doublons, ne désigne pas un responsable (F-67). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $takenBy = null;

    /** Indicateur « X rédige une réponse » (F-68), valable quelques minutes. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $composingBy = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $composingSince = null;

    /** @var Collection<int, SuiviNote> */
    #[ORM\OneToMany(mappedBy: 'suivi', targetEntity: SuiviNote::class, cascade: ['persist'])]
    #[ORM\OrderBy(['at' => 'ASC'])]
    private Collection $notes;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $subject = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $contact = '';

    #[ORM\Column(length: 20, enumType: SuiviDirection::class)]
    private SuiviDirection $direction = SuiviDirection::Waiting;

    #[ORM\Column(length: 20, enumType: SuiviStatus::class)]
    private SuiviStatus $status = SuiviStatus::New;

    /** Échéance propre à la demande (F-56), distincte du délai de relance. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private \DateTimeImmutable $dueAt;

    /** Prochaine relance PROPOSÉE (F-53) ; jamais envoyée seule (F-55). */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeImmutable $relanceAt = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $relanceCount = 0;

    /** « Ne plus relancer » (F-55). */
    #[ORM\Column]
    private bool $noRelance = false;

    /** null pour un alias : aucun responsable désigné (F-67). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $responsible = null;

    /** 'personal' ou identifiant d'une boîte partagée. */
    #[ORM\Column(length: 60)]
    private string $mailbox = 'personal';

    /** Identifiant du message IMAP (Message-ID) : dédoublonnage des alias (F-44). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mailId = null;

    /** Une réponse est arrivée : le suivi passe « à traiter », sans clôture automatique (F-65). */
    #[ORM\Column]
    private bool $replyReceived = false;

    #[ORM\Column]
    private bool $suggestedByAi = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    /** @var Collection<int, JournalEntry> */
    #[ORM\OneToMany(mappedBy: 'suivi', targetEntity: JournalEntry::class, cascade: ['persist'])]
    #[ORM\OrderBy(['at' => 'ASC'])]
    private Collection $journalEntries;

    public function __construct()
    {
        $this->journalEntries = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->id = Uuid::v7()->toRfc4122();
        $this->dueAt = new \DateTimeImmutable('today');
    }

    public function getId(): string { return $this->id; }
    #[Ignore]
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $owner): static { $this->owner = $owner; return $this; }
    #[Ignore] public function getSharedMailbox(): ?SharedMailbox { return $this->sharedMailbox; }
    public function setSharedMailbox(?SharedMailbox $m): static { $this->sharedMailbox = $m; $this->mailbox = $m?->getSlug() ?? 'personal'; return $this; }
    #[Ignore] public function getTakenBy(): ?User { return $this->takenBy; }
    public function setTakenBy(?User $u): static { $this->takenBy = $u; return $this; }
    /** Nom affiché de la personne qui a pris la demande (alias seulement). */
    public function getTakenByName(): ?string { return $this->takenBy?->getDisplayName(); }
    #[Ignore] public function getComposingBy(): ?User { return $this->composingBy; }
    #[Ignore] public function getComposingSince(): ?\DateTimeImmutable { return $this->composingSince; }
    public function setComposing(?User $u, ?\DateTimeImmutable $at = null): static { $this->composingBy = $u; $this->composingSince = $u === null ? null : ($at ?? new \DateTimeImmutable()); return $this; }
    /** Personne en train de rédiger, si l'indicateur est encore frais (2 minutes). */
    public function getComposingName(?\DateTimeImmutable $now = null): ?string
    {
        $now ??= new \DateTimeImmutable();

        return $this->composingBy !== null && $this->composingSince !== null && $this->composingSince > $now->modify('-2 minutes') ? $this->composingBy->getDisplayName() : null;
    }
    /** Notes internes : réservées aux membres de l'alias, donc vides pour une demande personnelle. @return list<array{id: string, author: string, text: string, at: string}> */
    public function getNotes(): array { return array_map(static fn (SuiviNote $n) => $n->toArray(), $this->notes->toArray()); }
    public function addNote(string $author, string $text): static { $this->notes->add(new SuiviNote($this, $author, $text)); return $this; }
    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $v): static { $this->subject = $v; return $this; }
    public function getContact(): string { return $this->contact; }
    public function setContact(string $v): static { $this->contact = $v; return $this; }
    public function getDirection(): SuiviDirection { return $this->direction; }
    public function setDirection(SuiviDirection $v): static { $this->direction = $v; return $this; }
    public function getStatus(): SuiviStatus { return $this->status; }
    public function setStatus(SuiviStatus $v): static { $this->status = $v; return $this; }
    public function getDueAt(): \DateTimeImmutable { return $this->dueAt; }
    public function setDueAt(\DateTimeImmutable $v): static { $this->dueAt = $v; return $this; }
    public function getRelanceAt(): ?\DateTimeImmutable { return $this->relanceAt; }
    public function setRelanceAt(?\DateTimeImmutable $v): static { $this->relanceAt = $v; return $this; }
    public function getRelanceCount(): int { return $this->relanceCount; }
    public function setRelanceCount(int $v): static { $this->relanceCount = $v; return $this; }
    public function isNoRelance(): bool { return $this->noRelance; }
    public function setNoRelance(bool $v): static { $this->noRelance = $v; return $this; }
    public function getResponsible(): ?string { return $this->responsible; }
    public function setResponsible(?string $v): static { $this->responsible = $v; return $this; }
    public function getMailbox(): string { return $this->mailbox; }
    public function setMailbox(string $v): static { $this->mailbox = $v; return $this; }
    public function getMailId(): ?string { return $this->mailId; }
    public function setMailId(?string $v): static { $this->mailId = $v; return $this; }
    public function isReplyReceived(): bool { return $this->replyReceived; }
    public function setReplyReceived(bool $v): static { $this->replyReceived = $v; return $this; }
    public function isSuggestedByAi(): bool { return $this->suggestedByAi; }
    public function setSuggestedByAi(bool $v): static { $this->suggestedByAi = $v; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $v): static { $this->reason = $v; return $this; }

    /**
     * Journal non modifiable (F-58), en lecture seule dans l'API.
     *
     * @return list<array{at: string, author: string, action: string}>
     */
    public function getJournal(): array
    {
        return array_map(static fn (JournalEntry $e) => $e->toArray(), $this->journalEntries->toArray());
    }

    public function log(string $author, string $action, ?\DateTimeImmutable $at = null): static
    {
        $this->journalEntries->add(new JournalEntry($this, $at ?? new \DateTimeImmutable(), $author, $action));

        return $this;
    }

    public function close(): static
    {
        $this->status = SuiviStatus::Done;
        $this->replyReceived = false;
        $this->log($this->owner?->getDisplayName() ?? '', 'closed');

        return $this;
    }
}
