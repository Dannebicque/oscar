<?php

namespace App\Mail;

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/** Lecture IMAP via webklex/php-imap (PHP pur, sans l'extension imap retirée du noyau). */
final class WebklexMailboxReader implements MailboxReader, MailboxAppender
{
    private ?\Webklex\PHPIMAP\Client $client = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $encryption = 'none',
        private readonly bool $validateCert = true,
    ) {
    }

    public function fetchAfter(string $folder, int $afterUid): array
    {
        $client = $this->client();
        $box = $client->getFolderByPath($folder);
        if ($box === null) {
            return [];
        }
        // « UID n+1:* » renvoie toujours le dernier message même s'il est plus ancien : on refiltre.
        $messages = $box->messages()->whereUid(($afterUid + 1).':*')->leaveUnread()->setFetchBody(false)->get();

        $out = [];
        foreach ($messages as $m) {
            if ((int) $m->getUid() > $afterUid) {
                $out[] = $this->toHeader($m, $folder);
            }
        }
        usort($out, static fn (MailHeader $a, MailHeader $b) => $a->uid <=> $b->uid);

        return $out;
    }

    public function append(string $folder, string $rawMessage): void
    {
        $box = $this->client()->getFolderByPath($folder) ?? throw new \RuntimeException("Dossier $folder introuvable.");
        $box->appendMessage($rawMessage, ['\\Seen']);
    }

    public function fetchBody(string $folder, int $uid): ?string
    {
        $box = $this->client()->getFolderByPath($folder);
        $m = $box?->messages()->whereUid((string) $uid)->leaveUnread()->setFetchBody(true)->get()->first();
        if ($m === null) {
            return null;
        }
        $text = trim((string) $m->getTextBody());
        if ($text === '') {
            $text = trim(html_entity_decode(strip_tags((string) preg_replace('#<(script|style)\b.*?</\1>#is', '', (string) $m->getHTMLBody())), ENT_QUOTES | ENT_HTML5));
        }

        return $text === '' ? null : $text;
    }

    private function client(): \Webklex\PHPIMAP\Client
    {
        if ($this->client === null) {
            $c = (new ClientManager())->make([
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption === 'none' ? false : $this->encryption,
                'validate_cert' => $this->validateCert,
                'username' => $this->user,
                'password' => $this->password,
                'protocol' => 'imap',
            ]);
            $c->connect();
            $this->client = $c;
        }

        return $this->client;
    }

    private function toHeader(Message $m, string $folder): MailHeader
    {
        $from = $m->getFrom()->first();
        $to = [];
        foreach ($m->getTo()->all() as $t) {
            $to[] = (string) ($t->mail ?? '');
        }
        $date = $m->getDate()->first();
        $raw = $m->getHeader()->raw ?? '';

        return new MailHeader(
            messageId: (string) $m->getMessageId(),
            inReplyTo: ($v = trim((string) $m->getInReplyTo())) !== '' ? $v : null,
            references: preg_split('/\s+/', trim((string) $m->getReferences()), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fromName: (string) ($from->personal ?? ''),
            fromAddress: (string) ($from->mail ?? ''),
            to: $to,
            subject: (string) $m->getSubject(),
            date: $date instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($date) : new \DateTimeImmutable(),
            autoSubmitted: ReplyDetector::looksAutomatic(
                self::rawHeader($raw, 'Auto-Submitted'),
                self::rawHeader($raw, 'Precedence'),
                self::rawHeader($raw, 'X-Auto-Response-Suppress'),
            ),
            uid: (int) $m->getUid(),
            folder: $folder,
        );
    }

    private static function rawHeader(string $raw, string $name): ?string
    {
        return preg_match('/^'.preg_quote($name, '/').':[ \t]*(.*)$/mi', $raw, $mm) === 1 ? trim($mm[1]) : null;
    }
}
