<?php

namespace App\Mail;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\User;
use App\Entity\Suivi;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use App\Repository\MailRepository;
use App\Repository\SuiviRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Synchronise une boîte et signale les réponses sur les suivis en attente.
 * Ne clôt jamais un suivi (F-65) et n'envoie rien.
 */
final class MailSynchronizer
{
    public const FOLDERS = ['INBOX', 'Sent'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailRepository $mails,
        private readonly SuiviRepository $suivis,
        private readonly ReplyDetector $detector = new ReplyDetector(),
    ) {
    }

    /** @return array{fetched: int, replies: int} */
    public function sync(MailAccount $account, MailboxReader $reader): array
    {
        $fetched = 0;
        foreach (self::FOLDERS as $folder) {
            foreach ($reader->fetchAfter($folder, $this->mails->lastUid($account, $folder)) as $header) {
                $this->em->persist(Mail::fromHeader($account, $header));
                ++$fetched;
            }
        }
        $account->markSynced();
        $this->em->flush();

        return ['fetched' => $fetched, 'replies' => $this->markReplies($account->getOwner())];
    }

    private function notifyReply(User $owner, Suivi $s, MailHeader $reply): void
    {
        $key = 'reply:'.$s->getId().':'.$reply->key();
        if ($this->em->getRepository(\App\Entity\Notification::class)->findOneBy(['owner' => $owner, 'dedupeKey' => $key]) === null) {
            $this->em->persist((new \App\Entity\Notification('replyReceived', ['who' => $reply->fromName !== '' ? $reply->fromName : $reply->fromAddress, 'subject' => $s->getSubject()], 'suivis', $key))->setOwner($owner));
        }
    }

    /** Un suivi « en attente » dont le fil a reçu une vraie réponse passe en « réponse reçue ». */
    public function markReplies(User $owner): int
    {
        $inbound = array_map(static fn (Mail $m) => $m->toHeader(), $this->mails->inbound($owner));
        $marked = 0;
        /** @var list<Suivi> $open */
        $open = $this->suivis->findBy(['owner' => $owner, 'direction' => SuiviDirection::Waiting, 'replyReceived' => false]);
        foreach ($open as $s) {
            if ($s->getStatus() === SuiviStatus::Done || $s->getMailId() === null) {
                continue;
            }
            if (($reply = $this->detector->firstReply($inbound, $s->getMailId())) !== null) {
                $s->setReplyReceived(true);
                $s->log($reply->fromName !== '' ? $reply->fromName : $reply->fromAddress, 'replyReceived', $reply->date);
                $this->notifyReply($owner, $s, $reply);
                ++$marked;
            }
        }
        $this->em->flush();

        return $marked;
    }
}
