<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Journal d'un suivi (F-58) : date, auteur, action. Ajout seul, jamais modifiable. */
#[ORM\Entity]
#[ORM\Index(columns: ['suivi_id', 'at'])]
class JournalEntry
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'journalEntries')] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Suivi $suivi,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $at,
        #[ORM\Column(length: 160)] private string $author,
        /** Code d'action (created, sent, take, status:done, relanceSent…) : traduit côté front. */
        #[ORM\Column(length: 120)] private string $action,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
    }

    /** @return array{at: string, author: string, action: string} */
    public function toArray(): array
    {
        return ['at' => $this->at->format(\DATE_ATOM), 'author' => $this->author, 'action' => $this->action];
    }
}
