<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Boîte partagée servie par un alias (contact@) : tous les membres reçoivent chaque mail, aucun responsable
 * n'est désigné (F-67). Seules les informations utiles au suivi sont partagées ; les boîtes personnelles restent privées (§2).
 */
#[ORM\Entity]
class SharedMailbox
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    /** Nom affiché quand un membre répond « De : contact@ » ; vide = le nom de la boîte. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $senderName = null;
    /** Signature de l'équipe, insérée à la rédaction d'une réponse depuis la boîte (texte brut). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $signature = null;

    /** @var Collection<int, SharedMailboxMember> */
    #[ORM\OneToMany(mappedBy: 'mailbox', targetEntity: SharedMailboxMember::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    public function __construct(
        /** Adresse de l'alias, en minuscules : reconnue dans les destinataires des mails. */
        #[ORM\Column(length: 180, unique: true)] private string $address,
        #[ORM\Column(length: 160)] private string $name,
        /** Identifiant court exposé au front (« contact »). */
        #[ORM\Column(length: 60, unique: true)] private string $slug,
        /** F-66 : tout mail arrivant sur l'alias crée une demande (règle visible, modifiable, désactivable). */
        #[ORM\Column] private bool $autoFollow = true,
        /** §5 : l'IA n'est activée sur une boîte partagée que par un administrateur. */
        #[ORM\Column] private bool $aiEnabled = false,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->address = strtolower($address);
        $this->members = new ArrayCollection();
    }

    public function getId(): string { return $this->id; }
    public function getAddress(): string { return $this->address; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getSenderName(): ?string { return $this->senderName; }
    public function setSenderName(?string $v): static { $this->senderName = $v === null || trim($v) === '' ? null : trim($v); return $this; }
    public function getSignature(): ?string { return $this->signature; }
    public function setSignature(?string $v): static { $this->signature = $v === null || trim($v) === '' ? null : $v; return $this; }
    public function isAutoFollow(): bool { return $this->autoFollow; }
    public function setAutoFollow(bool $v): static { $this->autoFollow = $v; return $this; }
    public function isAiEnabled(): bool { return $this->aiEnabled; }
    public function setAiEnabled(bool $v): static { $this->aiEnabled = $v; return $this; }
    /** @return Collection<int, SharedMailboxMember> */
    public function getMembers(): Collection { return $this->members; }

    /** @return list<SharedMailboxMember> les membres qui ont accepté : les seuls à voir et à partager */
    public function getAcceptedMembers(): array { return array_values(array_filter($this->members->toArray(), static fn (SharedMailboxMember $m) => $m->isAccepted())); }

    /** Membre effectif (invitation acceptée), celui qui a des droits. */
    public function memberOf(User $u): ?SharedMailboxMember
    {
        $m = $this->membershipOf($u);

        return $m?->isAccepted() ? $m : null;
    }

    /** Membre ou personne invitée. */
    public function membershipOf(User $u): ?SharedMailboxMember
    {
        foreach ($this->members as $m) {
            if ($m->getUser()->getId() === $u->getId()) {
                return $m;
            }
        }

        return null;
    }

    public function addMember(User $u, string $role = 'member'): SharedMailboxMember
    {
        if (($existing = $this->membershipOf($u)) !== null) {
            return $existing;
        }
        $m = new SharedMailboxMember($this, $u, $role);
        $this->members->add($m);

        return $m;
    }
}
