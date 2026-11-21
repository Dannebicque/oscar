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
                    if ($routed === null) {
                        $this->sort($account->getOwner(), $mail, $reader);
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

    /** Règles de tri (F-15) : range un mail reçu dans les archives ou la corbeille, jamais s'il a un suivi, jamais de suppression définitive. */
    private function sort(User $owner, Mail $mail, MailboxReader $reader): void
    {
        if (!$reader instanceof \App\Mail\MailboxMover || $this->suivis->findOneBy(['owner' => $owner, 'mailId' => $mail->getMessageId()]) !== null) {
            return;
        }
        foreach ($this->em->getRepository(\App\Entity\SortRule::class)->findBy(['owner' => $owner, 'enabled' => true]) as $rule) {
            if (!$rule->matches($mail->getFromAddress(), $mail->getSubject())) {
                continue;
            }
            $target = $rule->getAction() === 'trash' ? 'Trash' : 'Archive';
            try {
                $uid = $reader->move($mail->getFolder(), $mail->getUid(), $mail->getMessageId(), $target);
                if ($uid !== null) {
                    $mail->relocate($target, $uid);
                }
            } catch (\Throwable) {
                // Le serveur n'a pas suivi : le mail reste dans la boîte de réception, rien n'est perdu.
            }

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
