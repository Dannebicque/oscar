<?php

namespace App\Mail;

use App\Entity\Mail;
use App\Entity\SharedMailbox;
use App\Entity\SharedMailboxMember;
use App\Entity\Suivi;
use App\Entity\User;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Un même message reçu par plusieurs membres d'un alias déclaré devient UNE demande (F-44), avec un seul état
 * visible de tous. La détection ne s'applique qu'aux messages adressés à un alias déclaré (§2).
 */
final class AliasRouter
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function route(User $owner, Mail $mail): ?Suivi
    {
        $box = $this->boxFor($owner, $mail->getToAddresses());
        if ($box === null) {
            return null;
        }
        $mail->setSharedMailbox($box);
        $existing = $this->em->getRepository(Suivi::class)->findOneBy(['sharedMailbox' => $box, 'mailId' => $mail->getMessageId()]);
        if ($existing !== null || !$box->isAutoFollow()) {
            return $existing; // déjà reconnue par un autre membre : pas de doublon
        }
        $who = $mail->getFromName() !== '' ? $mail->getFromName() : $mail->getFromAddress();
        $s = (new Suivi())->setSharedMailbox($box)->setSubject(preg_replace('/^(re|tr)\s*:\s*/i', '', $mail->getSubject()) ?? $mail->getSubject())
            ->setContact($who)->setDirection(SuiviDirection::ToHandle)->setStatus(SuiviStatus::New)->setMailId($mail->getMessageId())
            ->setDueAt($mail->getDate()->modify('+2 days')->setTime(0, 0));
        $s->log($who, 'received', $mail->getDate());
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    /** @param list<string> $recipients */
    private function boxFor(User $owner, array $recipients): ?SharedMailbox
    {
        $recipients = array_map('strtolower', $recipients);
        foreach ($this->em->getRepository(SharedMailboxMember::class)->findBy(['user' => $owner]) as $member) {
            if (\in_array($member->getMailbox()->getAddress(), $recipients, true)) {
                return $member->getMailbox();
            }
        }

        return null;
    }
}
