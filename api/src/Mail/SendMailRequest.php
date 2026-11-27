<?php

namespace App\Mail;

use Symfony\Component\Validator\Constraints as Assert;

/** Demande d'envoi (F-73). `follow` crée le suivi à l'envoi (case « Suivre la réponse »). */
final class SendMailRequest
{
    /** Instant ISO 8601 avec fuseau, avec ou sans fractions de seconde. */
    private const INSTANT = '/^\d{4}-\d\d-\d\dT\d\d:\d\d(:\d\d(\.\d+)?)?(Z|[+-]\d\d:\d\d)$/D';

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
        /** @var list<array{name: string, type: string, content: string}> pièces jointes en base64 (somme limitée par compte, 10 Mo par défaut) */
        #[Assert\Count(max: 10)] public array $attachments = [],
        /** Identité d'expéditeur (nom, adresse « De », « Répondre à ») ; absente = identité par défaut du compte. */
        public ?string $identityId = null,
        /** @var list<array{start: string, end: string}> créneaux à poser « en provisoire » dans Oscar si le message part (F-74) ; décoché par défaut */
        #[Assert\Count(max: 8)] #[Assert\All([new Assert\Collection(fields: ['start' => new Assert\Regex(self::INSTANT), 'end' => new Assert\Regex(self::INSTANT)])])] public array $holds = [],
    ) {
    }
}
