<?php

namespace App\Recap;

use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\RecapLog;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Mail\OutgoingMailer;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Envoie, une seule fois par créneau et par jour, le récap du matin et le bilan du soir (F-01, F-03). */
final class RecapService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RecapBuilder $builder,
        private readonly OutgoingMailer $mailer,
        private readonly RecapText $text,
    ) {
    }

    /** @return list<string> créneaux traités pendant cet appel */
    public function dispatchDue(User $user, \DateTimeImmutable $nowUtc): array
    {
        $settings = ($this->em->getRepository(UserSettings::class)->findOneBy(['owner' => $user]) ?? new UserSettings($user))->all();
        $local = $nowUtc->setTimezone(new \DateTimeZone((string) $settings['timezone']));
        $done = [];
        foreach (['morning' => 'recapMorning', 'evening' => 'recapEvening'] as $slot => $key) {
            if ($local->format('H:i') < $settings[$key] || !$this->claim($user, $slot, $local->format('Y-m-d'))) {
                continue;
            }
            $content = $this->builder->build($user, $local, $settings);
            if ($settings['recapEmail']) {
                $this->email($user, $slot, $content, (string) $settings['locale'], $local);
            }
            $done[] = $slot;
        }

        return $done;
    }

    /** Réserve le créneau : si deux workers passent en même temps, un seul gagne (contrainte d'unicité). */
    private function claim(User $user, string $slot, string $day): bool
    {
        if ($this->em->getRepository(RecapLog::class)->findOneBy(['owner' => $user, 'slot' => $slot, 'day' => $day]) !== null) {
            return false;
        }
        try {
            $this->em->persist(new RecapLog($user, $slot, $day));
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    private function email(User $user, string $slot, array $content, string $locale, \DateTimeImmutable $local): void
    {
        $account = $this->em->getRepository(MailAccount::class)->findOneBy(['owner' => $user]);
        if ($account === null) {
            return;
        }
        $mail = (new Email())->from(new Address($account->getAddress(), 'Oscar'))->to($account->getAddress())
            ->subject($this->text->subject($slot, $locale, $local))->text($this->text->body($slot, $content, $locale));
        $mail->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
        try {
            $this->mailer->send($account, $mail);
        } catch (\Throwable $e) {
            // Le récap reste disponible dans l'application ; l'échec est visible dans le journal des erreurs.
            $this->em->persist((new \App\Entity\LogEntry($account->getLabel(), 'send', 'recapNotSent', 'retry'))->setOwner($user));
            $this->em->flush();
        }
    }
}
