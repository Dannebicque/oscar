<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Événement d'un agenda (F-20). Lecture par synchro CalDAV ; la création écrit d'abord sur le serveur.
 * L'IA ne lit que libre/occupé : elle n'a pas accès aux titres ni aux lieux (§5).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'event_uid', columns: ['calendar_id', 'uid'])]
#[ORM\Index(columns: ['owner_id', 'starts_at'])]
#[ApiResource(operations: [new GetCollection(), new Get()], formats: ['json' => ['application/json']], order: ['start' => 'ASC'])]
class CalendarEvent implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CalendarAccount $calendar = null;

    #[ORM\Column(length: 255)]
    private string $uid;

    #[ORM\Column(length: 300)] #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(name: 'starts_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $start;

    #[ORM\Column(name: 'ends_at', type: Types::DATETIME_IMMUTABLE)] #[Assert\GreaterThan(propertyPath: 'start')]
    private \DateTimeImmutable $end;

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $location = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->uid = Uuid::v4()->toRfc4122().'@oscar';
        $this->start = new \DateTimeImmutable();
        $this->end = $this->start->modify('+1 hour');
    }

    public function getId(): string { return $this->id; }
    #[Ignore] public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $o): static { $this->owner = $o; return $this; }
    #[Ignore] public function getCalendar(): ?CalendarAccount { return $this->calendar; }
    public function setCalendar(CalendarAccount $c): static { $this->calendar = $c; return $this; }
    /** Identifiant de l'agenda, tel que le front le référence. */
    public function getCalendarId(): ?string { return $this->calendar?->getId(); }
    #[Ignore] public function getUid(): string { return $this->uid; }
    public function setUid(string $v): static { $this->uid = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getStart(): \DateTimeImmutable { return $this->start; }
    public function setStart(\DateTimeImmutable $v): static { $this->start = $v; return $this; }
    public function getEnd(): \DateTimeImmutable { return $this->end; }
    public function setEnd(\DateTimeImmutable $v): static { $this->end = $v; return $this; }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $v): static { $this->location = $v; return $this; }
}
