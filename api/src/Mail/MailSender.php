<?php

namespace App\Mail;

use App\Entity\Mail;
use App\Entity\MailAccount;
use App\Entity\Suivi;
use App\Entity\User;
use App\Enum\SuiviDirection;
use App\Enum\SuiviStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoie un message puis, SEULEMENT s'il est parti, crée le suivi (F-50, F-73).
 * Si l'envoi échoue, rien n'est créé : pas de suivi sur un mail qui n'a pas quitté la boîte.
 */
final class MailSender
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OutgoingMailer $mailer,
        private readonly MailboxReaderFactory $factory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return array{messageId: string, suivi: ?Suivi, savedToSent: bool} */
    public function send(User $user, MailAccount $account, SendMailRequest $r, ?Mail $replyTo = null, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $email = (new Email())
            ->from(new Address($account->getAddress(), $user->getDisplayName()))
            ->to(...array_map(static fn (string $a) => new Address($a), $r->to))
            ->subject($r->subject)
            ->text($r->body)
            ->date($now);
        if ($r->cc !== []) {
            $email->cc(...array_map(static fn (string $a) => new Address($a), $r->cc));
        }
        if ($r->bcc !== []) {
            $email->bcc(...array_map(static fn (string $a) => new Address($a), $r->bcc));
        }
        foreach ($r->attachments as $a) {
            $raw = base64_decode((string) ($a['content'] ?? ''), true);
            if ($raw === false) {
                throw new \InvalidArgumentException('Pièce jointe illisible.');
            }
            $email->attach($raw, mb_substr((string) ($a['name'] ?? 'pièce-jointe'), 0, 150), 'application/octet-stream');
        }
        $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(12)), substr(strrchr($account->getAddress(), '@') ?: '@localhost', 1));
        $email->getHeaders()->addIdHeader('Message-ID', trim($messageId, '<>'));
        if ($replyTo !== null) {
            $parents = [...$replyTo->getReferences(), $replyTo->getMessageId()];
            $email->getHeaders()->addTextHeader('In-Reply-To', $replyTo->getMessageId());
            $email->getHeaders()->addTextHeader('References', implode(' ', array_unique($parents)));
        }

        $this->mailer->send($account, $email); // lève une exception si le message n'est pas parti : on s'arrête ici.

        $saved = $this->saveToSent($account, $email);

        if ($replyTo !== null && $replyTo->getSharedMailbox() !== null) {
            $this->recordTeamReply($user, $replyTo, $now);
        }

        $suivi = null;
        if ($r->followHours !== null) {
            $due = $now->modify(sprintf('+%d hours', $r->followHours))->setTime(0, 0);
            $suivi = (new Suivi())->setOwner($user)->setSubject(preg_replace('/^(re|tr)\s*:\s*/i', '', $r->subject) ?? $r->subject)
                ->setContact($this->contactName($r->to[0]))->setDirection(SuiviDirection::Waiting)->setStatus(SuiviStatus::Waiting)
                ->setDueAt($due)->setRelanceAt($due)->setMailId($messageId)->setResponsible($user->getDisplayName())
                ->setSuggestedByAi($r->followSuggestedByAi);
            $suivi->log($user->getDisplayName(), 'created', $now)->log($user->getDisplayName(), 'sent', $now);
            $this->em->persist($suivi);
            $this->em->flush();
        }

        return ['messageId' => $messageId, 'suivi' => $suivi, 'savedToSent' => $saved];
    }

    /** Réponse envoyée depuis une boîte partagée : journal de la demande et information des autres membres (F-68). */
    private function recordTeamReply(User $user, Mail $original, \DateTimeImmutable $now): void
    {
        $box = $original->getSharedMailbox();
        $team = $this->em->getRepository(Suivi::class)->findOneBy(['sharedMailbox' => $box, 'mailId' => $original->getMessageId()]);
        if ($team === null) {
            return;
        }
        $team->log($user->getDisplayName(), 'sent', $now)->setComposing(null);
        foreach ($box->getMembers() as $member) {
            if ($member->getUser()->getId() !== $user->getId()) {
                $this->em->persist((new \App\Entity\Notification('memberReplied', ['who' => $user->getDisplayName(), 'subject' => $team->getSubject()], 'suivis', 'memberreply:'.$team->getId().':'.$now->format('c').':'.$member->getUser()->getId(), $now))->setOwner($member->getUser()));
            }
        }
        $this->em->flush();
    }

    /** Une copie dans Envoyés : un échec ne doit pas faire croire que le message n'est pas parti. */
    private function saveToSent(MailAccount $account, Email $email): bool
    {
        try {
            $this->factory->create($account)->append('Sent', $email->toString());

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Copie dans Envoyés impossible : '.$e->getMessage());

            return false;
        }
    }

    private function contactName(string $address): string
    {
        $local = explode('@', $address)[0];

        return ucwords(str_replace(['.', '_', '-'], ' ', $local));
    }
}
