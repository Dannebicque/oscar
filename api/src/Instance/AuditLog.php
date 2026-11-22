<?php

namespace App\Instance;

use App\Entity\AuditEntry;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Écrit dans le journal d'audit de l'instance (administration seulement). */
final class AuditLog
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @param array<string, scalar|list<string>> $details */
    public function record(User $actor, string $action, ?string $target = null, array $details = []): void
    {
        $this->em->persist(new AuditEntry($actor->getDisplayName().' <'.($actor->getEmail() ?? $actor->getUsername()).'>', $action, $target, $details));
        $this->em->flush();
    }
}
