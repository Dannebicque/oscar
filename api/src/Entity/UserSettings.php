<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Réglages d'un utilisateur utiles au serveur (relances, récaps, mode d'IA). Thème et densité restent côté appareil. */
#[ORM\Entity]
class UserSettings
{
    public const DEFAULTS = [
        'relanceHours' => 48, 'businessDays' => true, 'dailyCap' => 5, 'recapMorning' => '08:00', 'recapEvening' => '17:30',
        'recapEmail' => true, 'recapHome' => true, 'includePersonalCalendar' => false, 'shareBusy' => false,
        'aiMode' => 'university', 'timezone' => 'Europe/Paris', 'locale' => 'fr', 'aiSuggestFollow' => true, 'aiSuggestTasks' => true, 'aiDraftRelance' => true,
        // Canaux par type de notification (F-75) : voir NotificationCenter::DEFAULTS.
        'notifyChannels' => [],
        // Annuaire (F-18) : champs affichés sous le nom, en plus de nom, prénom et e-mail.
        'dirShowDepartment' => true, 'dirShowPhone' => false,
    ];

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    /** @var array<string, scalar> */
    #[ORM\Column(name: 'setting_values', type: Types::JSON)]
    private array $values = [];

    public function __construct(#[ORM\OneToOne] #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')] private User $owner)
    {
        $this->id = Uuid::v7()->toRfc4122();
    }

    /** @return array<string, scalar> réglages complets, valeurs par défaut comprises */
    public function all(): array
    {
        return array_replace(self::DEFAULTS, $this->values);
    }

    /** @param array<string, scalar> $changes */
    public function apply(array $changes): void
    {
        $this->values = array_replace($this->values, array_intersect_key($changes, self::DEFAULTS));
    }

    public function getOwner(): User { return $this->owner; }
}
