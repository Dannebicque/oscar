<?php

namespace App\Mail;

use Symfony\Component\Validator\Constraints as Assert;

/** Programmer l'envoi d'un message : le message et l'heure choisie par l'utilisateur. */
final class ScheduleMailRequest
{
    public function __construct(
        #[Assert\Valid] public SendMailRequest $mail = new SendMailRequest(),
        #[Assert\NotBlank] public string $sendAt = '',
    ) {
    }
}
