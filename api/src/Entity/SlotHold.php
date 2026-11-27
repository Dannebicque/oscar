<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Créneau « en provisoire » (F-74) : proposé dans une réponse réellement partie, en attente d'un choix. Gardé dans Oscar seul, jamais écrit
 * sur le serveur d'agenda. Lié au message envoyé, au message auquel on répondait et au suivi. Seul un créneau passé disparaît de lui-même.
 */
#[ORM\Entity]
#[ORM\Index(columns: ['owner_id', 'ends_at'])]
class SlotHold
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Suivi $suivi = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $owner,
        /** Les créneaux d'une même réponse vont ensemble : en retenir un libère les autres. */
        #[ORM\Column(type: Types::GUID)] private string $groupId,
        #[ORM\Column(name: 'starts_at')] private \DateTimeImmutable $start,
        #[ORM\Column(name: 'ends_at')] private \DateTimeImmutable $end,
        #[ORM\Column(length: 200)] private string $contact,
        #[ORM\Column(length: 500)] private string $subject,
        #[ORM\Column(length: 400)] private string $mailId,
        #[ORM\Column(length: 400, nullable: true)] private ?string $repliedToMailId = null,
        ?Suivi $suivi = null,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->suivi = $suivi;
        $this->createdAt = $now ?? new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getGroupId(): string { return $this->groupId; }
    public function getStart(): \DateTimeImmutable { return $this->start; }
    public function getEnd(): \DateTimeImmutable { return $this->end; }

    /** @return array<string, ?string> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'groupId' => $this->groupId, 'start' => $this->start->format(\DATE_ATOM), 'end' => $this->end->format(\DATE_ATOM),
            'contact' => $this->contact, 'subject' => $this->subject, 'mailId' => $this->mailId, 'repliedToMailId' => $this->repliedToMailId,
            'suiviId' => $this->suivi?->getId(), 'createdAt' => $this->createdAt->format(\DATE_ATOM),
        ];
    }
}
