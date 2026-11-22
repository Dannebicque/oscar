<?php

namespace App\Instance;

use App\Entity\InstanceSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Configuration globale de l'instance, réglée par l'administrateur (écran Administration ou assistant d'installation).
 * Ordre de priorité : valeur enregistrée, puis valeur de l'environnement quand elle existe (APP_REGISTRATION), puis valeur par défaut.
 * Les secrets (clés, mots de passe de serveurs) ne passent jamais par ici : ils restent dans l'environnement.
 */
final class InstanceConfig
{
    /** Réglages connus : type, valeur par défaut. Tout autre nom est refusé. */
    public const SCHEMA = [
        'instanceName' => ['string', 'Oscar'],
        'registration' => ['enum:open,closed', null], // défaut : APP_REGISTRATION
        'supportEmail' => ['string', ''],
        'legalUrl' => ['url', ''],
        'privacyUrl' => ['url', ''],
        'defaultAttachmentMb' => ['int:1,25', 10],
        'aiExternalAllowed' => ['bool', true],
        'setupDone' => ['bool', false],
    ];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(APP_REGISTRATION)%')] private readonly string $envRegistration,
    ) {
    }

    /** @return array<string, mixed> tous les réglages, valeurs par défaut comprises */
    public function all(): array
    {
        if ($this->cache === null) {
            $stored = [];
            foreach ($this->em->getRepository(InstanceSetting::class)->findAll() as $s) {
                $stored[$s->getKey()] = $s->getValue();
            }
            $this->cache = [];
            foreach (self::SCHEMA as $key => [, $default]) {
                $this->cache[$key] = \array_key_exists($key, $stored) ? $stored[$key] : ($key === 'registration' ? ($this->envRegistration === 'open' ? 'open' : 'closed') : $default);
            }
        }

        return $this->cache;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function name(): string { return (string) $this->get('instanceName'); }
    public function registrationOpen(): bool { return $this->get('registration') === 'open'; }
    public function aiExternalAllowed(): bool { return (bool) $this->get('aiExternalAllowed'); }
    public function defaultAttachmentMb(): int { return (int) $this->get('defaultAttachmentMb'); }

    /**
     * Enregistre des réglages après validation ; renvoie les erreurs par champ (rien n'est enregistré s'il y en a).
     *
     * @param array<string, mixed> $changes
     * @return array<string, string>
     */
    public function apply(array $changes): array
    {
        $errors = [];
        $clean = [];
        foreach ($changes as $key => $value) {
            if (!isset(self::SCHEMA[$key])) {
                continue; // nom inconnu : ignoré
            }
            $v = self::validate(self::SCHEMA[$key][0], $value);
            if ($v instanceof \Closure) {
                $errors[$key] = $v();
            } else {
                $clean[$key] = $v;
            }
        }
        if ($errors !== []) {
            return $errors;
        }
        foreach ($clean as $key => $value) {
            $row = $this->em->find(InstanceSetting::class, $key);
            if ($row === null) {
                $this->em->persist(new InstanceSetting($key, $value));
            } else {
                $row->setValue($value);
            }
        }
        $this->em->flush();
        $this->cache = null;

        return [];
    }

    /** @return mixed la valeur nettoyée, ou une fonction qui renvoie le message d'erreur */
    private static function validate(string $type, mixed $v): mixed
    {
        if ($type === 'bool') {
            return \is_bool($v) ? $v : static fn () => 'Attendu : oui ou non.';
        }
        if ($type === 'string') {
            $s = \is_string($v) ? trim($v) : null;

            return $s !== null && mb_strlen($s) <= 100 && !preg_match('/[\r\n<>]/', $s) ? $s : static fn () => 'Texte de 100 caractères au plus, sans retour à la ligne ni < >.';
        }
        if ($type === 'url') {
            $s = \is_string($v) ? trim($v) : null;

            return $s !== null && ($s === '' || (mb_strlen($s) <= 300 && preg_match('~^https://[^\s<>"]+$~i', $s))) ? $s : static fn () => 'Adresse en https:// attendue, ou vide.';
        }
        if (str_starts_with($type, 'enum:')) {
            $choices = explode(',', substr($type, 5));

            return \is_string($v) && \in_array($v, $choices, true) ? $v : static fn () => 'Choix possibles : '.implode(', ', $choices).'.';
        }
        if (str_starts_with($type, 'int:')) {
            [$min, $max] = array_map('intval', explode(',', substr($type, 4)));

            return \is_int($v) && $v >= $min && $v <= $max ? $v : static fn () => sprintf('Nombre entier de %d à %d.', $min, $max);
        }

        return static fn () => 'Réglage invalide.';
    }
}
