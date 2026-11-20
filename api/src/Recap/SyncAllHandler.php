<?php

namespace App\Recap;

use App\Entity\User;
use App\Mail\SyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncAllHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly SyncService $sync)
    {
    }

    public function __invoke(SyncAllMessage $m): void
    {
        foreach ($this->em->getRepository(User::class)->findAll() as $user) {
            $this->sync->syncUser($user);
            $this->em->clear(); // un utilisateur après l'autre : mémoire stable sur de grosses boîtes (N-07)
        }
    }
}
