<?php

namespace App\Domain;

/**
 * Délai de relance (F-12, F-54) : en heures réelles, ou en jours ouvrés (samedi et dimanche ne comptent pas).
 * Le délai est arrondi au jour supérieur ; l'échéance tombe toujours un jour ouvré en mode « jours ouvrés ».
 */
final class FollowUpDelay
{
    public static function dueDate(\DateTimeImmutable $from, int $hours, bool $businessDays): \DateTimeImmutable
    {
        if (!$businessDays) {
            return $from->modify(sprintf('+%d hours', $hours))->setTime(0, 0);
        }
        $d = $from->setTime(0, 0);
        for ($left = max(1, (int) ceil($hours / 24)); $left > 0;) {
            $d = $d->modify('+1 day');
            if ((int) $d->format('N') < 6) {
                --$left;
            }
        }

        return $d;
    }
}
