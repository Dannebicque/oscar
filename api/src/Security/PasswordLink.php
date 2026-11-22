<?php

namespace App\Security;

use App\Entity\User;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Lien pour choisir un mot de passe, envoyé PAR E-MAIL à l'utilisateur seulement (invitation ou réinitialisation demandée par un administrateur).
 * Le lien n'est jamais renvoyé à l'administrateur : sinon il pourrait prendre la main sur le compte et lire les mails de l'utilisateur.
 */
final class PasswordLink
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(FRONT_URL)%')] private readonly string $frontUrl,
        #[Autowire('%env(MAILER_FROM)%')] private readonly string $from,
    ) {
    }

    /** @return bool vrai si le courriel est parti (faux : pas d'adresse, ou serveur de courriel indisponible) */
    public function send(User $user, string $kind, string $instanceName): bool
    {
        $to = $user->getEmail();
        if ($to === null) {
            return false;
        }
        $token = bin2hex(random_bytes(32));
        $this->cache->save($this->cache->getItem('pwdreset_'.hash('sha256', $token))->set($user->getId())->expiresAfter($kind === 'invite' ? 7 * 86400 : 3600));
        $link = rtrim($this->frontUrl, '/').'/mot-de-passe/reinitialiser#token='.$token;
        [$subject, $text] = $kind === 'invite'
            ? ["Ton compte $instanceName est prêt", "Bonjour,\n\nUn compte $instanceName a été créé pour toi. Choisis ton mot de passe avec ce lien, valable sept jours et à usage unique :\n\n$link\n\n$instanceName"]
            : ["Réinitialiser ton mot de passe $instanceName", "Bonjour,\n\nUn administrateur a demandé la réinitialisation de ton mot de passe. Ce lien est valable une heure et ne sert qu'une fois :\n\n$link\n\nSi tu ne t'y attendais pas, ignore ce message : rien ne change.\n\n$instanceName"];
        try {
            $this->mailer->send((new Email())->from($this->from)->to($to)->subject($subject)->text($text));

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Courriel de lien de mot de passe non envoyé : '.$e->getMessage());

            return false;
        }
    }
}
