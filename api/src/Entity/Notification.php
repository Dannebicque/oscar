<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;

/** Notification dans l'application (F-75). Le texte est composé côté front à partir du type et des paramètres (fr/en). */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'notification_dedupe', columns: ['owner_id', 'dedupe_key'])]
#[ApiResource(operations: [new GetCollection()], formats: ['json' => ['application/json']], order: ['at' => 'DESC', 'id' => 'DESC'])]
class Notification implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column]
    private bool $read = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $at;

    /** Canaux choisis par l'utilisateur pour ce type (F-75) : panneau de l'application, et notification du bureau (lue par le client Tauri). */
    #[ORM\Column(options: ['default' => true])]
    private bool $inApp = true;
    #[ORM\Column(options: ['default' => true])]
    private bool $desktop = true;

    /**
     * @param array<string, scalar> $params
     * @param string                $to        écran visé : suivis, journal, agenda…
     * @param string                $dedupeKey évite d'alerter deux fois pour le même fait (N-08)
     */
    public function __construct(
        #[ORM\Column(length: 30)] private string $kind,
        #[ORM\Column(type: Types::JSON)] private array $params,
        #[ORM\Column(name: 'target', length: 30)] private string $to,
        #[ORM\Column(length: 200)] private string $dedupeKey,
        ?\DateTimeImmutable $at = null,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->at = $at ?? new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    #[Ignore] public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $o): static { $this->owner = $o; return $this; }
    public function getKind(): string { return $this->kind; }
    /** @return array<string, scalar> */
    public function getParams(): array { return $this->params; }
    public function getTo(): string { return $this->to; }
    #[Ignore] public function getDedupeKey(): string { return $this->dedupeKey; }
    public function setChannels(bool $inApp, bool $desktop): static { $this->inApp = $inApp; $this->desktop = $desktop; return $this; }
    public function isInApp(): bool { return $this->inApp; }
    public function isDesktop(): bool { return $this->desktop; }
    public function isRead(): bool { return $this->read; }
    public function markRead(): void { $this->read = true; }
    public function getAt(): \DateTimeImmutable { return $this->at; }
}
