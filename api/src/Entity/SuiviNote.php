<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Note interne (F-69) : visible des seuls membres de l'alias, jamais envoyée à l'extérieur. */
#[ORM\Entity]
class SuiviNote
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $at;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'notes')] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Suivi $suivi,
        #[ORM\Column(length: 160)] private string $author,
        #[ORM\Column(type: Types::TEXT)] private string $text,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->at = new \DateTimeImmutable();
    }

    /** @return array{id: string, author: string, text: string, at: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'author' => $this->author, 'text' => $this->text, 'at' => $this->at->format(\DATE_ATOM)];
    }
}
