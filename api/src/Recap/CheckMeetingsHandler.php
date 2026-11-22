<?php

namespace App\Recap;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckMeetingsHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly MeetingNotifier $notifier)
    {
    }

    public function __invoke(CheckMeetingsMessage $m): void
    {
        $now = new \DateTimeImmutable();
        foreach ($this->em->getRepository(User::class)->findAll() as $user) {
            $this->notifier->notify($user, $now);
        }
    }
}
