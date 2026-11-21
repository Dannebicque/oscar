<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Modèle de message (F-19) : un texte qu'on insère à la rédaction. Variables : {prenom} (destinataire) et {moi}. */
#[ORM\Entity]
class MessageTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $owner,
        #[ORM\Column(length: 80)] private string $name,
        #[ORM\Column(type: Types::TEXT)] private string $body,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function setBody(string $v): static { $this->body = $v; return $this; }

    /** @return array{id: string, name: string, body: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'body' => $this->body];
    }
}
