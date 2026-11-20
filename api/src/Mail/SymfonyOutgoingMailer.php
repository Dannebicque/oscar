<?php

namespace App\Mail;

use App\Entity\MailAccount;
use App\Security\CredentialVault;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

/** SMTP via Symfony Mailer. Le DSN est construit à l'envoi : le mot de passe n'est déchiffré qu'ici. */
final class SymfonyOutgoingMailer implements OutgoingMailer
{
    public function __construct(private readonly CredentialVault $vault)
    {
    }

    public function send(MailAccount $account, Email $email): void
    {
        (new Mailer(Transport::fromDsn($this->dsn($account))))->send($email);
    }

    public function dsn(MailAccount $a): string
    {
        $scheme = $a->getSmtpEncryption() === 'ssl' ? 'smtps' : 'smtp';
        $auth = rawurlencode($a->getUsername()).':'.rawurlencode($this->vault->decrypt($a->getPasswordEncrypted()));
        // « none » : serveur de test local, sans TLS ni vérification.
        $opts = $a->getSmtpEncryption() === 'none' ? '?auto_tls=false&verify_peer=0' : '';

        return sprintf('%s://%s@%s:%d%s', $scheme, $auth, $a->getSmtpHost(), $a->getSmtpPort(), $opts);
    }
}
