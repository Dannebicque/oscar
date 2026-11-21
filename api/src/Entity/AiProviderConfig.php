<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Fournisseur d'IA externe choisi par un utilisateur (§5). La clé est chiffrée hors base (CredentialVault) et ne revient jamais de l'API. */
#[ORM\Entity]
class AiProviderConfig
{
    public const KINDS = ['openai', 'anthropic'];

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    /** openai : toute API compatible (OpenAI, Mistral, Ollama, Groq…) ; anthropic : API Messages. */
    #[ORM\Column(length: 20)]
    private string $kind = 'openai';
    #[ORM\Column(length: 255)]
    private string $baseUrl = '';
    #[ORM\Column(length: 120)]
    private string $model = '';
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $apiKeyEncrypted = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(#[ORM\OneToOne] #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')] private User $owner)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getKind(): string { return $this->kind; }
    public function getBaseUrl(): string { return $this->baseUrl; }
    public function getModel(): string { return $this->model; }
    public function getApiKeyEncrypted(): ?string { return $this->apiKeyEncrypted; }
    public function hasKey(): bool { return $this->apiKeyEncrypted !== null && $this->apiKeyEncrypted !== ''; }
    public function isComplete(): bool { return $this->baseUrl !== '' && $this->model !== ''; }

    public function configure(string $kind, string $baseUrl, string $model): static
    {
        $this->kind = $kind;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function setApiKeyEncrypted(?string $v): static { $this->apiKeyEncrypted = $v; $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
