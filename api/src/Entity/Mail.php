<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Mail\MailHeader;
use App\Repository\MailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;

/**
 * En-têtes d'un message synchronisé (pas le contenu). Un même Message-ID reçu dans plusieurs boîtes
 * donne plusieurs lignes, regroupées en une seule demande (F-44). Lecture seule côté API.
 */
#[ORM\Entity(repositoryClass: MailRepository::class)]
#[ORM\UniqueConstraint(name: 'mail_position', columns: ['account_id', 'folder', 'uid'])]
#[ORM\Index(columns: ['message_id'])]
#[ApiResource(operations: [new GetCollection(), new Get()], formats: ['json' => ['application/json']], order: ['date' => 'DESC'])]
class Mail implements OwnedInterface
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private MailAccount $account,
        #[ORM\Column(length: 60)] private string $folder,
        #[ORM\Column] private int $uid,
        #[ORM\Column(length: 255)] private string $messageId,
        #[ORM\Column(length: 255, nullable: true)] private ?string $inReplyTo,
        /** @var list<string> */
        #[ORM\Column(name: 'thread_references', type: Types::JSON)] private array $references,
        #[ORM\Column(length: 255)] private string $fromName,
        #[ORM\Column(length: 255)] private string $fromAddress,
        /** @var list<string> */
        #[ORM\Column(type: Types::JSON)] private array $toAddresses,
        #[ORM\Column(length: 500)] private string $subject,
        #[ORM\Column(name: 'received_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $date,
        #[ORM\Column] private bool $autoSubmitted = false,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
    }

    public static function fromHeader(MailAccount $account, MailHeader $h): self
    {
        return (new self($account, $h->folder, $h->uid ?? 0, $h->messageId, $h->inReplyTo, $h->references, $h->fromName, $h->fromAddress, $h->to, mb_substr($h->subject, 0, 500), $h->date, $h->autoSubmitted))->setOwner($account->getOwner());
    }

    #[Ignore]
    public function toHeader(): MailHeader
    {
        return new MailHeader($this->messageId, $this->inReplyTo, $this->references, $this->fromName, $this->fromAddress, $this->toAddresses, $this->subject, $this->date, $this->autoSubmitted, $this->uid, $this->folder);
    }

    public function getId(): string { return $this->id; }
    #[Ignore]
    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $owner): static { $this->owner = $owner; return $this; }
    #[Ignore] public function getAccount(): MailAccount { return $this->account; }
    public function getAccountId(): string { return $this->account->getId(); }
    public function getFolder(): string { return $this->folder; }
    public function getUid(): int { return $this->uid; }
    public function getMessageId(): string { return $this->messageId; }
    public function getInReplyTo(): ?string { return $this->inReplyTo; }
    /** @return list<string> */
    public function getReferences(): array { return $this->references; }
    public function getFromName(): string { return $this->fromName; }
    public function getFromAddress(): string { return $this->fromAddress; }
    /** @return list<string> */
    public function getToAddresses(): array { return $this->toAddresses; }
    public function getSubject(): string { return $this->subject; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function isAutoSubmitted(): bool { return $this->autoSubmitted; }
}
