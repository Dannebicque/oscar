<?php

namespace App\Tests;

use App\Ai\ExternalAiProvider;
use App\Ai\HeuristicProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** §5 : lecture des réponses des fournisseurs externes. */
final class ExternalAiProviderTest extends TestCase
{
    private function provider(string $kind, array $payload): ExternalAiProvider
    {
        return new ExternalAiProvider(new MockHttpClient(new MockResponse((string) json_encode($payload))), new HeuristicProvider(new \App\Ai\FreeSlotFinder()), $kind, 'https://ia.example', 'm', 'k');
    }

    public function testAnthropicTextIsFoundEvenAfterAnotherBlock(): void
    {
        $p = $this->provider('anthropic', ['content' => [['type' => 'thinking', 'thinking' => '…'], ['type' => 'text', 'text' => 'Bonjour, je reviens vers toi.']]]);

        self::assertSame('Bonjour, je reviens vers toi.', $p->draftRelance('Marc', 'Devis', 'David', 'fr'));
    }

    public function testOpenAiCompatibleAnswerIsRead(): void
    {
        $p = $this->provider('openai', ['choices' => [['message' => ['content' => 'Relance prête.']]]]);

        self::assertSame('Relance prête.', $p->draftRelance('Marc', 'Devis', 'David', 'fr'));
    }

    public function testSaysWhatReallyCameFromTheModel(): void
    {
        $answer = ['choices' => [['message' => ['content' => '{"summary": "Demande un devis.", "task": null, "event": null, "suggestFollow": true}']]]];
        $a = $this->provider('openai', $answer)->analyse('Devis', 'Peux-tu envoyer le devis avant mardi ?', new \DateTimeImmutable('2026-11-27'), []);
        self::assertSame(['summary', 'suggestFollow'], $a->fromModel);

        $broken = $this->provider('openai', ['oups' => true]);
        self::assertSame([], $broken->analyse('Devis', 'Peux-tu envoyer le devis avant mardi ?', new \DateTimeImmutable('2026-11-27'), [])->fromModel);
        $broken->draftRelance('Marc', 'Devis', 'David', 'fr');
        self::assertFalse($broken->answeredByModel(), 'le repli local n’est pas présenté comme de l’IA');
        $ok = $this->provider('openai', ['choices' => [['message' => ['content' => 'Relance.']]]]);
        $ok->draftRelance('Marc', 'Devis', 'David', 'fr');
        self::assertTrue($ok->answeredByModel());
    }

    public function testAnAnswerWithoutTextIsReportedByTheConnectionTest(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->provider('anthropic', ['content' => []])->ping();
    }
}
