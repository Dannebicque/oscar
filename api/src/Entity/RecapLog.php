<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Un récap par créneau et par jour : la contrainte d'unicité garantit « aucun récap en double » (N-08). */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'recap_once', columns: ['owner_id', 'slot', 'day'])]
class RecapLog
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $owner,
        #[ORM\Column(length: 10)] private string $slot,
        #[ORM\Column(length: 10)] private string $day,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->sentAt = new \DateTimeImmutable();
    }
}
