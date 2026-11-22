<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Réglage global de l'instance (nom, inscriptions, limites…) : géré par l'administrateur, lu par tous. Voir InstanceConfig. */
#[ORM\Entity]
#[ORM\Table(name: 'instance_setting')]
class InstanceSetting
{
    public function __construct(
        #[ORM\Id] #[ORM\Column(name: 'setting_key', length: 60)] private string $key,
        #[ORM\Column(name: 'setting_value', type: Types::JSON)] private mixed $value,
    ) {
    }

    public function getKey(): string { return $this->key; }
    public function getValue(): mixed { return $this->value; }
    public function setValue(mixed $v): static { $this->value = $v; return $this; }
}
