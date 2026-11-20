<?php

namespace App\Mail;

/** En-têtes utiles au suivi d'un message (F-52, F-44). Aucun contenu de mail ici. */
final readonly class MailHeader
{
    /**
     * @param list<string> $references identifiants de la chaîne du fil
     * @param list<string> $to
     */
    public function __construct(
        public string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public string $fromName,
        public string $fromAddress,
        public array $to,
        public string $subject,
        public \DateTimeImmutable $date,
        public bool $autoSubmitted = false,
        public ?int $uid = null,
        public string $folder = 'INBOX',
    ) {
    }

    /** Identifiant normalisé : sans chevrons ni casse, pour comparer des exemplaires d'un même message. */
    public static function normalizeId(string $id): string
    {
        return strtolower(trim($id, " \t\n\r<>"));
    }

    public function key(): string
    {
        return self::normalizeId($this->messageId);
    }

    /** @return list<string> identifiants normalisés auxquels ce message répond */
    public function parents(): array
    {
        $ids = $this->references;
        if ($this->inReplyTo !== null) {
            $ids[] = $this->inReplyTo;
        }

        return array_values(array_unique(array_map(self::normalizeId(...), $ids)));
    }
}
