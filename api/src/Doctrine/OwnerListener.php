<?php

namespace App\Doctrine;

use App\Entity\OwnedInterface;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/** Une donnée créée via l'API appartient à son auteur ; la synchro, elle, fixe le propriétaire explicitement. */
#[AsDoctrineListener(event: Events::prePersist)]
final class OwnerListener
{
    public function __construct(private readonly Security $security)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $e = $args->getObject();
        if ($e instanceof OwnedInterface && $e->getOwner() === null && ($u = $this->security->getUser()) instanceof User) {
            $e->setOwner($u);
        }
    }
}
