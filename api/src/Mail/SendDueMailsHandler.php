<?php

namespace App\Mail;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\ScheduledMail;
use App\Notification\NotificationCenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendDueMailsHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly MailSender $sender, private readonly NotificationCenter $center)
    {
    }

    public function __invoke(SendDueMailsMessage $m): int
    {
        $now = new \DateTimeImmutable();
        $sent = 0;
        $due = $this->em->createQuery('SELECT s FROM '.ScheduledMail::class.' s WHERE s.status = :p AND s.sendAt <= :now ORDER BY s.sendAt')
            ->setParameters(['p' => ScheduledMail::PENDING, 'now' => $now])->getResult();
        foreach ($due as $s) {
            /** @var ScheduledMail $s */
            // « sending » d'abord : deux passages du planificateur n'envoient jamais deux fois le même message.
            $s->setStatus(ScheduledMail::SENDING);
            $this->em->flush();
            $r = $s->getRequest();
            $request = new SendMailRequest(
                $r['accountId'] ?? '', $r['to'] ?? [], $r['cc'] ?? [], $r['bcc'] ?? [], $r['subject'] ?? '', $r['body'] ?? '',
                $r['inReplyToMailId'] ?? null, $r['followHours'] ?? null, (bool) ($r['followSuggestedByAi'] ?? false), $r['attachments'] ?? [], $r['identityId'] ?? null,
            );
            $owner = $s->getOwner();
            try {
                $account = $this->em->find(MailAccount::class, $request->accountId);
                if ($account === null || $account->getOwner()?->getId() !== $owner->getId()) {
                    throw new \RuntimeException('compte');
                }
                $reply = $request->inReplyToMailId !== null ? $this->em->find(Mail::class, $request->inReplyToMailId) : null;
                $this->sender->send($owner, $account, $request, $reply?->getOwner()?->getId() === $owner->getId() ? $reply : null, $now);
                $this->em->remove($s);
                ++$sent;
            } catch (\Throwable) {
                // Rien n'est parti : on le dit, sans humour, avec la marche à suivre ; le message reste visible pour être relancé ou supprimé.
                $s->setStatus(ScheduledMail::FAILED);
                $this->center->deliver($owner, new Notification('scheduledFailed', ['subject' => $request->subject], 'mails', 'scheduled:'.$s->getId(), $now));
            }
            $this->em->flush();
        }

        return $sent;
    }
}
