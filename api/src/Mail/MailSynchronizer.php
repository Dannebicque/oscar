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
    public const FOLDERS = ['INBOX', 'Sent', 'Drafts'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailRepository $mails,
        private readonly SuiviRepository $suivis,
        private readonly AliasRouter $aliases,
        private readonly \App\Notification\NotificationCenter $center,
        private readonly ReplyDetector $detector = new ReplyDetector(),
    ) {
    }

    /** @return array{fetched: int, replies: int} */
    public function sync(MailAccount $account, MailboxReader $reader): array
    {
        $fetched = 0;
        foreach (self::FOLDERS as $folder) {
            foreach ($reader->fetchAfter($folder, $this->mails->lastUid($account, $folder)) as $header) {
                $mail = Mail::fromHeader($account, $header);
                $this->em->persist($mail);
                if ($header->folder === 'INBOX') {
                    $routed = $this->aliases->route($account->getOwner(), $mail);
                    if ($routed === null && !$header->autoSubmitted) {
                        $this->applyRules($account->getOwner(), $mail);
                    }
                }
                ++$fetched;
            }
        }
        $account->markSynced();
        $this->em->flush();

        return ['fetched' => $fetched, 'replies' => $this->markReplies($account->getOwner())];
    }

    /** Règles de création automatique (F-66) : un expéditeur ou un domaine suivi d'office, jamais pour un message automatique ni déjà suivi. */
    private function applyRules(User $owner, Mail $mail): void
    {
        $rules = $this->em->getRepository(\App\Entity\FollowRule::class)->findBy(['owner' => $owner, 'enabled' => true]);
        foreach ($rules as $rule) {
            if (!$rule->matches($mail->getFromAddress())) {
                continue;
            }
            if ($this->suivis->findOneBy(['owner' => $owner, 'mailId' => $mail->getMessageId()]) !== null) {
                return;
            }
            $who = $mail->getFromName() !== '' ? $mail->getFromName() : $mail->getFromAddress();
            $s = (new Suivi())->setOwner($owner)->setSubject(preg_replace('/^(re|tr)\s*:\s*/i', '', $mail->getSubject()) ?? $mail->getSubject())
                ->setContact($who)->setDirection(SuiviDirection::ToHandle)->setStatus(SuiviStatus::New)->setMailId($mail->getMessageId())
                ->setResponsible($owner->getDisplayName())->setDueAt($mail->getDate()->modify('+2 days')->setTime(0, 0));
            $s->log($who, 'received', $mail->getDate());
            $this->em->persist($s);

            return;
        }
    }

    private function notifyReply(User $owner, Suivi $s, MailHeader $reply): void
    {
        $key = 'reply:'.$s->getId().':'.$reply->key();
        $this->center->deliver($owner, new \App\Entity\Notification('replyReceived', ['who' => $reply->fromName !== '' ? $reply->fromName : $reply->fromAddress, 'subject' => $s->getSubject()], 'suivis', $key));
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
