<?php

namespace App\Recap;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckRecapsHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly RecapService $recaps)
    {
    }

    public function __invoke(CheckRecapsMessage $m): void
    {
        $now = new \DateTimeImmutable();
        foreach ($this->em->getRepository(User::class)->findBy(['disabled' => false]) as $user) {
            $this->recaps->dispatchDue($user, $now);
        }
    }
}
