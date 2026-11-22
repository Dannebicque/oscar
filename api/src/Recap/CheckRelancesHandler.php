<?php

namespace App\Recap;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckRelancesHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly RelanceNotifier $notifier)
    {
    }

    public function __invoke(CheckRelancesMessage $m): void
    {
        $now = new \DateTimeImmutable();
        foreach ($this->em->getRepository(User::class)->findBy(['disabled' => false]) as $user) {
            $this->notifier->notify($user, $now);
        }
    }
}
