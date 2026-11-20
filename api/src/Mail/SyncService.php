<?php

namespace App\Mail;

use App\Calendar\CalendarSynchronizer;
use App\Entity\CalendarAccount;
use App\Entity\MailAccount;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Synchronise tous les comptes d'un utilisateur : à la demande (« Réessayer ») ou par le planificateur. */
final class SyncService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailSynchronizer $mails,
        private readonly MailboxReaderFactory $readers,
        private readonly CalendarSynchronizer $calendars,
        private readonly ConnectionMonitor $monitor,
    ) {
    }

    /** @return list<array{kind: string, account: string, ok: bool}> */
    public function syncUser(User $user): array
    {
        $out = [];
        foreach ($this->em->getRepository(MailAccount::class)->findBy(['owner' => $user]) as $a) {
            $out[] = ['kind' => 'mail', 'account' => $a->getLabel(), 'ok' => $this->run($a, 'sync', fn () => $this->mails->sync($a, $this->readers->create($a)))];
        }
        foreach ($this->em->getRepository(CalendarAccount::class)->findBy(['owner' => $user]) as $a) {
            $out[] = ['kind' => 'calendar', 'account' => $a->getLabel(), 'ok' => $this->run($a, 'calendar', fn () => $this->calendars->sync($a))];
        }

        return $out;
    }

    private function run(\App\Entity\SyncableAccount $account, string $type, callable $job): bool
    {
        try {
            $job();
            $this->monitor->success($account);

            return true;
        } catch (\Throwable $e) {
            $this->monitor->failure($account, $type, $e);

            return false;
        }
    }
}
