<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Identité d'expéditeur (F-19) : le nom affiché, l'adresse « De », l'adresse « Répondre à » et la signature, par compte de messagerie.
 * Chaque compte a une identité principale (son adresse) ; on peut en ajouter pour un alias ou une fonction.
 * Le serveur d'envoi doit autoriser l'adresse choisie : sinon le message ne part pas et Oscar le dit.
 */
#[ORM\Entity]
#[ORM\Index(columns: ['owner_id'])]
class Identity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;
    #[ORM\Column(options: ['default' => false])]
    private bool $isDefault = false;
    /** Signature en HTML assaini (liste blanche) ; `signature` en garde alors l'équivalent texte, inséré à la rédaction. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $signatureHtml = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $owner,
        #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private MailAccount $account,
        #[ORM\Column(length: 100)] private string $name,
        #[ORM\Column(length: 180)] private string $address,
        #[ORM\Column(length: 180, nullable: true)] private ?string $replyTo = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)] private ?string $signature = null,
        /** Identité principale du compte : son adresse, non supprimable. */
        #[ORM\Column(name: 'is_primary', options: ['default' => false])] private bool $primary = false,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getAccount(): MailAccount { return $this->account; }
    public function setAccount(MailAccount $a): static { $this->account = $a; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getAddress(): string { return $this->address; }
    public function setAddress(string $v): static { $this->address = $v; return $this; }
    public function getReplyTo(): ?string { return $this->replyTo; }
    public function setReplyTo(?string $v): static { $this->replyTo = $v === null || trim($v) === '' ? null : trim($v); return $this; }
    public function getSignature(): ?string { return $this->signature; }
    public function setSignature(?string $v): static { $this->signature = $v === null || trim($v) === '' ? null : $v; return $this; }
    public function getSignatureHtml(): ?string { return $this->signatureHtml; }
    public function setSignatureHtml(?string $v): static { $this->signatureHtml = $v === null || trim($v) === '' ? null : $v; return $this; }
    public function isDefault(): bool { return $this->isDefault; }
    public function setDefault(bool $v): static { $this->isDefault = $v; return $this; }
    public function isPrimary(): bool { return $this->primary; }

    /** @return array{id: string, accountId: string, name: string, address: string, replyTo: ?string, signature: ?string, signatureHtml: ?string, isDefault: bool, primary: bool} */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'accountId' => $this->account->getId(), 'name' => $this->name, 'address' => $this->address, 'replyTo' => $this->replyTo,
            'signature' => $this->signature, 'signatureHtml' => $this->signatureHtml, 'isDefault' => $this->isDefault, 'primary' => $this->primary,
        ];
    }
}
