<?php

namespace App\Notification;

use App\Entity\MailAccount;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Remise des notifications (F-75), canal par type d'événement : dans l'application, bureau (le client Tauri les lit et les affiche) et e-mail.
 * Une seule alerte par fait (N-08) : la clé de dédoublonnage protège aussi l'e-mail, même quand le canal « application » est coupé.
 */
final class NotificationCenter
{
    public const TYPES = ['replyReceived', 'relanceDue', 'invitation', 'connectionFailed'];
    public const CHANNELS = ['app', 'desktop', 'email'];
    /** Par défaut : dans l'application et sur le bureau ; l'e-mail seulement pour une connexion en échec persistante. */
    public const DEFAULTS = [
        'replyReceived' => ['app' => true, 'desktop' => true, 'email' => false],
        'relanceDue' => ['app' => true, 'desktop' => true, 'email' => false],
        'invitation' => ['app' => true, 'desktop' => true, 'email' => false],
        'connectionFailed' => ['app' => true, 'desktop' => true, 'email' => true],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')] private readonly string $from,
    ) {
    }

    /** @return array<string, array<string, bool>> canaux effectifs de l'utilisateur */
    public function channelsFor(User $user): array
    {
        $saved = ($this->em->getRepository(UserSettings::class)->findOneBy(['owner' => $user]) ?? new UserSettings($user))->all()['notifyChannels'] ?? [];
        $out = self::DEFAULTS;
        foreach (self::TYPES as $t) {
            foreach (self::CHANNELS as $c) {
                if (\is_array($saved) && isset($saved[$t][$c]) && \is_bool($saved[$t][$c])) {
                    $out[$t][$c] = $saved[$t][$c];
                }
            }
        }

        return $out;
    }

    /** @return bool vrai si la notification a été remise par au moins un canal (donc pas un doublon) */
    public function deliver(User $owner, Notification $n, bool $flush = false): bool
    {
        $channels = $this->channelsFor($owner)[$n->getKind()] ?? ['app' => true, 'desktop' => false, 'email' => false];
        $seen = $this->cache->getItem('notified_'.sha1($owner->getId().'|'.$n->getDedupeKey()));
        $known = $this->em->getRepository(Notification::class)->findOneBy(['owner' => $owner, 'dedupeKey' => $n->getDedupeKey()]) !== null;
        if ($known || $seen->isHit()) {
            return false;
        }
        if ($channels['app'] || $channels['desktop']) {
            // Le client bureau lit la même liste : le canal « bureau » seul garde donc la ligne, masquée dans le panneau par `desktopOnly`.
            $this->em->persist($n->setOwner($owner)->setChannels($channels['app'], $channels['desktop']));
        }
        if ($channels['email']) {
            $this->email($owner, $n);
        }
        $this->cache->save($seen->set(1)->expiresAfter(40 * 86400));
        if ($flush) {
            $this->em->flush();
        }

        return true;
    }

    private function email(User $owner, Notification $n): void
    {
        $to = $owner->getEmail() ?? $this->em->getRepository(MailAccount::class)->findOneBy(['owner' => $owner])?->getAddress();
        if ($to === null) {
            return;
        }
        $locale = (string) (($this->em->getRepository(UserSettings::class)->findOneBy(['owner' => $owner]) ?? new UserSettings($owner))->all()['locale'] ?? 'fr');
        [$subject, $body] = $this->compose($n, $locale === 'en' ? 'en' : 'fr');
        try {
            $this->mailer->send((new Email())->from($this->from)->to($to)->subject($subject)->text($body."\n\nOscar"));
        } catch (\Throwable $e) {
            $this->logger->warning('Notification par e-mail non envoyée : '.$e->getMessage());
        }
    }

    /** @return array{string, string} */
    private function compose(Notification $n, string $l): array
    {
        $p = $n->getParams();
        $who = (string) ($p['who'] ?? '');
        $subject = (string) ($p['subject'] ?? '');

        return match ($n->getKind()) {
            'replyReceived' => $l === 'en' ? ['Reply received', "$who replied to “$subject”. Open Oscar to close the request or keep it open."] : ['Réponse reçue', "$who a répondu à « $subject ». Ouvre Oscar pour clore la demande ou la garder ouverte."],
            'relanceDue' => (int) ($p['n'] ?? 1) > 1
                ? ($l === 'en' ? ['Reminders due', (int) $p['n'].' reminders are ready to review. Nothing is sent without your click.'] : ['Relances dues', (int) $p['n'].' relances sont prêtes à relire. Rien ne part sans ton clic.'])
                : ($l === 'en' ? ['Reminder due', "$who has not replied to “$subject”. A draft is ready, nothing is sent without your click."] : ['Relance due', "$who n’a pas répondu à « $subject ». Un brouillon est prêt, rien ne part sans ton clic."]),
            'connectionFailed' => $l === 'en' ? ['Connection problem', 'Oscar cannot reach “'.(string) ($p['account'] ?? '').'”. Check the account in Settings.'] : ['Connexion en échec', 'Oscar n’arrive pas à joindre « '.(string) ($p['account'] ?? '').' ». Vérifie le compte dans Réglages.'],
            default => $l === 'en' ? ['Notification', 'Open Oscar to see what is waiting.'] : ['Notification', 'Ouvre Oscar pour voir ce qui t’attend.'],
        };
    }
}
