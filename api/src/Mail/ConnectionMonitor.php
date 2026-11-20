<?php

namespace App\Mail;

use App\Entity\LogEntry;
use App\Entity\Notification;
use App\Entity\SyncableAccount;
use Doctrine\ORM\EntityManagerInterface;

/**
 * État des connexions (F-77) et journal des erreurs (F-78). Une alerte n'est envoyée qu'après
 * des échecs répétés, une seule fois par jour et par compte, pour éviter le bruit (F-79, N-08).
 */
final class ConnectionMonitor
{
    public const ALERT_AFTER = 3;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function success(SyncableAccount $account): void
    {
        $account->markSynced();
        $this->em->flush();
    }

    public function failure(SyncableAccount $account, string $type, \Throwable $e, ?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();
        $account->markFailed($e->getMessage());
        $owner = $account->getOwner();
        if ($owner !== null) {
            $this->em->persist((new LogEntry($account->getLabel(), $type, $this->humanize($e), $this->humanize($e) === 'authRefused' ? 'reconnect' : 'retry'))->setOwner($owner));
            if ($account->getFailureCount() >= self::ALERT_AFTER) {
                $this->notifyOnce($owner, new Notification('connectionFailed', ['account' => $account->getLabel()], 'journal', 'conn:'.$account->getLabel().':'.$now->format('Y-m-d'), $now));
            }
        }
        $this->em->flush();
    }

    /**
     * Code de la cause, jamais la trace technique (F-79) : le front le traduit en français ou en anglais
     * (`journal.messages.<code>`) avec l'action à faire.
     */
    private function humanize(\Throwable $e): string
    {
        $m = strtolower($e->getMessage());

        return match (true) {
            str_contains($m, 'auth') || str_contains($m, 'login') || str_contains($m, 'credential') || str_contains($m, '401') => 'authRefused',
            str_contains($m, 'timed out') || str_contains($m, 'timeout') => 'timeout',
            str_contains($m, 'refused') || str_contains($m, 'resolve') || str_contains($m, 'connect') => 'unreachable',
            default => 'failed',
        };
    }

    private function notifyOnce(\App\Entity\User $owner, Notification $n): void
    {
        if ($this->em->getRepository(Notification::class)->findOneBy(['owner' => $owner, 'dedupeKey' => $n->getDedupeKey()]) === null) {
            $this->em->persist($n->setOwner($owner));
        }
    }
}
