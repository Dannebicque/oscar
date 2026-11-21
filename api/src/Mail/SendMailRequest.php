<?php

namespace App\Mail;

use Symfony\Component\Validator\Constraints as Assert;

/** Demande d'envoi (F-73). `follow` crée le suivi à l'envoi (case « Suivre la réponse »). */
final class SendMailRequest
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     */
    public function __construct(
        #[Assert\NotBlank] public string $accountId = '',
        #[Assert\Count(min: 1, max: 50)] #[Assert\All([new Assert\Email()])] public array $to = [],
        #[Assert\Count(max: 50)] #[Assert\All([new Assert\Email()])] public array $cc = [],
        #[Assert\Count(max: 50)] #[Assert\All([new Assert\Email()])] public array $bcc = [],
        #[Assert\NotBlank] #[Assert\Length(max: 500)] public string $subject = '',
        #[Assert\Length(max: 200000)] public string $body = '',
        /** Mail auquel on répond : fixe In-Reply-To et References, donc le fil. */
        public ?string $inReplyToMailId = null,
        /** Délai de relance en heures ; null = ne pas suivre. */
        #[Assert\Range(min: 1, max: 24 * 60)] public ?int $followHours = null,
        /** La case a été précochée par l'IA (affichage « Suggéré par l'IA »). */
        public bool $followSuggestedByAi = false,
        /** @var list<array{name: string, type: string, content: string}> pièces jointes en base64 (5 Mo au total) */
        #[Assert\Count(max: 10)] public array $attachments = [],
    ) {
    }
}
