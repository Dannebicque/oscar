<?php

namespace App\Mail;

use App\Entity\MailAccount;
use Symfony\Component\Mime\Email;

/** Envoi SMTP au nom d'un compte. Toujours déclenché par un clic de l'utilisateur, jamais par l'IA. */
interface OutgoingMailer
{
    /** @throws \Throwable si le serveur refuse ou ne répond pas */
    public function send(MailAccount $account, Email $email): void;
}
