<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\CalendarAccountProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** Agenda CalDAV d'un utilisateur. Le connecteur Google viendra derrière la même interface (version 2). */
#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(), new Get(), new Post(processor: CalendarAccountProcessor::class),
        new Patch(inputFormats: ['json' => ['application/merge-patch+json', 'application/json']], processor: CalendarAccountProcessor::class), new Delete(),
    ],
    formats: ['json' => ['application/json']],
)]
class CalendarAccount implements OwnedInterface, SyncableAccount
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 160)] #[Assert\NotBlank]
    private string $label = '';

    #[ORM\Column(length: 10)] #[Assert\Choice(choices: ['caldav', 'google'])]
    private string $kind = 'caldav';

    /** URL de la collection CalDAV (https obligatoire hors serveur de test local). */
    #[ORM\Column(length: 500)] #[Assert\NotBlank] #[Assert\Url(requireTld: false)]
    private string $url = '';

    #[ORM\Column(length: 180)]
    private string $username = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $passwordEncrypted = '';

    #[ApiProperty(readable: false, writable: true)]
    private ?string $password = null;

    /** Agenda perso : exclu de « Prochain RDV », des priorités et du tableau de suivi sauf réglage (F-71). */
    #[ORM\Column]
    private bool $personal = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;
    #[ORM\Column]
    private int $failureCount = 0;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
    }

    public function getId(): string { return $this->id; }
    #[Ignore] public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $o): static { $this->owner = $o; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }
    public function getKind(): string { return $this->kind; }
    public function setKind(string $v): static { $this->kind = $v; return $this; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $v): static { $this->url = $v; return $this; }
    public function getUsername(): string { return $this->username; }
    public function setUsername(string $v): static { $this->username = $v; return $this; }
    #[Ignore] public function getPasswordEncrypted(): string { return $this->passwordEncrypted; }
    public function setPasswordEncrypted(string $v): static { $this->passwordEncrypted = $v; return $this; }
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $v): static { $this->password = $v; return $this; }
    public function isPersonal(): bool { return $this->personal; }
    public function setPersonal(bool $v): static { $this->personal = $v; return $this; }
    public function getLastSyncAt(): ?\DateTimeImmutable { return $this->lastSyncAt; }
    public function getLastError(): ?string { return $this->lastError; }
    #[Ignore] public function getFailureCount(): int { return $this->failureCount; }

    public function markSynced(): void { $this->lastSyncAt = new \DateTimeImmutable(); $this->lastError = null; $this->failureCount = 0; }
    public function markFailed(string $m): void { $this->lastError = mb_substr($m, 0, 500); ++$this->failureCount; }
}
