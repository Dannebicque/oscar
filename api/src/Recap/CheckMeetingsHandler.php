<?php

namespace App\Recap;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckMeetingsHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly MeetingNotifier $notifier, private readonly \App\Instance\Heartbeat $heartbeat)
    {
    }

    public function __invoke(CheckMeetingsMessage $m): void
    {
        $now = new \DateTimeImmutable();
        $this->heartbeat->beat(); // preuve que le worker et le planificateur tournent (écran Santé)
        foreach ($this->em->getRepository(User::class)->findBy(['disabled' => false]) as $user) {
            $this->notifier->notify($user, $now);
        }
    }
}
