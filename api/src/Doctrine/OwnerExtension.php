<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\OwnedInterface;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/** Chaque requête de l'API est restreinte aux données de l'utilisateur connecté (isolation, §2). */
final class OwnerExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $qb, QueryNameGeneratorInterface $names, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->restrict($qb, $resourceClass);
    }

    public function applyToItem(QueryBuilder $qb, QueryNameGeneratorInterface $names, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->restrict($qb, $resourceClass);
    }

    private function restrict(QueryBuilder $qb, string $resourceClass): void
    {
        if (!is_subclass_of($resourceClass, OwnedInterface::class)) {
            return;
        }
        $user = $this->security->getUser();
        $alias = $qb->getRootAliases()[0];
        if (!$user instanceof User) {
            $qb->andWhere('1 = 0'); // pas d'utilisateur : rien n'est visible
            return;
        }
        $qb->andWhere(sprintf('%s.owner = :current_owner', $alias))->setParameter('current_owner', $user->getId());
    }
}
