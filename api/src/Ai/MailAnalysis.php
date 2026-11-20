<?php

namespace App\Ai;

/** Ce que l'IA propose pour un mail. Des suggestions uniquement : rien n'est créé sans clic (F-64). */
final readonly class MailAnalysis
{
    /**
     * @param ?list<array{start: string, end: string}>         $slots
     * @param ?array{title: string, start: string, end: string, quote: string} $event
     * @param ?array{title: string, dueAt: string, quote: string}              $task
     */
    public function __construct(
        public ?array $slots = null,
        public ?array $event = null,
        public ?array $task = null,
        public bool $suggestFollow = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->slots === null && $this->event === null && $this->task === null && !$this->suggestFollow;
    }

    /** @return array<string, mixed> forme attendue par le front (`hints`) */
    public function toArray(): array
    {
        return [
            'availability' => $this->slots === null ? null : ['slots' => $this->slots],
            'event' => $this->event, 'task' => $this->task, 'suggestFollow' => $this->suggestFollow,
        ];
    }
}
