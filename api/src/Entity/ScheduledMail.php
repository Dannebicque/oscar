<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Mail programmé (F-73) : l'utilisateur a écrit le message et choisi explicitement quand il part.
 * Rien ne part sans ce choix ; tant que l'heure n'est pas venue, il peut l'annuler.
 */
#[ORM\Entity]
#[ORM\Index(name: 'scheduled_mail_due', columns: ['status', 'send_at'])]
class ScheduledMail
{
    public const PENDING = 'pending';
    public const SENDING = 'sending';
    public const FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;
    #[ORM\Column(length: 10)]
    private string $status = self::PENDING;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $request demande d'envoi telle que l'API la reçoit (SendMailRequest) */
    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $owner,
        #[ORM\Column(type: Types::JSON)] private array $request,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $sendAt,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function getSendAt(): \DateTimeImmutable { return $this->sendAt; }
    /** @return array<string, mixed> */
    public function getRequest(): array { return $this->request; }

    /** @return array{id: string, subject: string, to: list<string>, sendAt: string, status: string, attachments: int} */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'subject' => (string) ($this->request['subject'] ?? ''), 'to' => array_values((array) ($this->request['to'] ?? [])),
            'sendAt' => $this->sendAt->format(\DATE_ATOM), 'status' => $this->status, 'attachments' => \count((array) ($this->request['attachments'] ?? [])),
        ];
    }
}
