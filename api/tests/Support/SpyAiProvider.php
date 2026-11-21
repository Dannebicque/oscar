<?php

namespace App\Tests\Support;

use App\Ai\AiProvider;
use App\Ai\HeuristicProvider;
use App\Ai\MailAnalysis;

/** Enregistre ce que le fournisseur reçoit : on vérifie qu'il ne reçoit rien de plus que le nécessaire. */
final class SpyAiProvider implements AiProvider
{
    /** @var list<array{subject: string, body: string, busy: array}> */
    public array $calls = [];

    public function name(): string { return 'spy'; }

    public function analyse(string $subject, string $body, \DateTimeImmutable $mailDate, array $busy): MailAnalysis
    {
        $this->calls[] = ['subject' => $subject, 'body' => $body, 'busy' => $busy];

        return (new HeuristicProvider())->analyse($subject, $body, $mailDate, $busy);
    }

    public function draftReply(string $contact, string $subject, string $me, string $locale, string $tone, array $slotLines): string
    {
        return (new HeuristicProvider())->draftReply($contact, $subject, $me, $locale, $tone, $slotLines);
    }

    public function draftRelance(string $contact, string $subject, string $me, string $locale): string
    {
        return (new HeuristicProvider())->draftRelance($contact, $subject, $me, $locale);
    }
}
