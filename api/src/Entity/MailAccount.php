<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\MailAccountProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** Compte de messagerie d'un utilisateur (IMAP + SMTP). Le mot de passe est chiffré hors base et jamais renvoyé. */
#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(), new Get(), new Post(processor: MailAccountProcessor::class),
        new Patch(inputFormats: ['json' => ['application/merge-patch+json', 'application/json']], processor: MailAccountProcessor::class), new Delete(),
    ],
    formats: ['json' => ['application/json']],
)]
class MailAccount implements OwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 160)] #[Assert\NotBlank]
    private string $label = '';

    /** Adresse de l'expéditeur (From). */
    #[ORM\Column(length: 180)] #[Assert\Email]
    private string $address = '';

    #[ORM\Column(length: 255)] #[Assert\NotBlank]
    private string $imapHost = '';
    #[ORM\Column] #[Assert\Range(min: 1, max: 65535)]
    private int $imapPort = 993;
    /** none | ssl | tls : « none » seulement pour un serveur de test local. */
    #[ORM\Column(length: 10)] #[Assert\Choice(choices: ['none', 'ssl', 'tls'])]
    private string $imapEncryption = 'ssl';
    #[ORM\Column(length: 255)] #[Assert\NotBlank]
    private string $smtpHost = '';
    #[ORM\Column] #[Assert\Range(min: 1, max: 65535)]
    private int $smtpPort = 587;
    #[ORM\Column(length: 10)] #[Assert\Choice(choices: ['none', 'ssl', 'tls'])]
    private string $smtpEncryption = 'tls';
    #[ORM\Column(length: 180)] #[Assert\NotBlank]
    private string $username = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $passwordEncrypted = '';

    /** Écriture seule : chiffré par le processeur, jamais persisté ni renvoyé en clair. */
    #[ApiProperty(readable: false, writable: true)]
    private ?string $password = null;

    /** L'IA peut être interdite sur certaines boîtes (confidentielles, RH), §5. */
    #[ORM\Column]
    private bool $aiAllowed = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
    }

    public function getId(): string { return $this->id; }
    #[Ignore] public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $owner): static { $this->owner = $owner; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }
    public function getAddress(): string { return $this->address; }
    public function setAddress(string $v): static { $this->address = $v; return $this; }
    public function getImapHost(): string { return $this->imapHost; }
    public function setImapHost(string $v): static { $this->imapHost = $v; return $this; }
    public function getImapPort(): int { return $this->imapPort; }
    public function setImapPort(int $v): static { $this->imapPort = $v; return $this; }
    public function getImapEncryption(): string { return $this->imapEncryption; }
    public function setImapEncryption(string $v): static { $this->imapEncryption = $v; return $this; }
    public function getSmtpHost(): string { return $this->smtpHost; }
    public function setSmtpHost(string $v): static { $this->smtpHost = $v; return $this; }
    public function getSmtpPort(): int { return $this->smtpPort; }
    public function setSmtpPort(int $v): static { $this->smtpPort = $v; return $this; }
    public function getSmtpEncryption(): string { return $this->smtpEncryption; }
    public function setSmtpEncryption(string $v): static { $this->smtpEncryption = $v; return $this; }
    public function getUsername(): string { return $this->username; }
    public function setUsername(string $v): static { $this->username = $v; return $this; }
    #[Ignore] public function getPasswordEncrypted(): string { return $this->passwordEncrypted; }
    public function setPasswordEncrypted(string $v): static { $this->passwordEncrypted = $v; return $this; }
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $v): static { $this->password = $v; return $this; }
    public function isAiAllowed(): bool { return $this->aiAllowed; }
    public function setAiAllowed(bool $v): static { $this->aiAllowed = $v; return $this; }
    public function getLastSyncAt(): ?\DateTimeImmutable { return $this->lastSyncAt; }
    public function getLastError(): ?string { return $this->lastError; }

    public function markSynced(): void { $this->lastSyncAt = new \DateTimeImmutable(); $this->lastError = null; }
    public function markFailed(string $message): void { $this->lastError = mb_substr($message, 0, 500); }
}
