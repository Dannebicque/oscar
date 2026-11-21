<?php

namespace App\Calendar;

/**
 * Agenda de démonstration (développement) : un emploi du temps de la semaine, recalculé autour d'aujourd'hui, pour voir l'agenda,
 * « Prochain RDV » et les conflits sans serveur CalDAV. Adresse de compte : `demo://iut` (pro) ou `demo://perso` (perso).
 * Les UID sont stables : une synchronisation met à jour sans doubler.
 */
final class DemoCalendar
{
    private const WORK = [
        // [jour ISO 1-7, début, fin, titre, lieu]
        [1, '09:00', '12:00', 'Cours Symfony', 'Salle info 2'],
        [1, '14:00', '15:30', 'Réunion de pôle', 'Salle des profs'],
        [2, '10:00', '11:00', 'Réunion pédagogique', 'Salle A3'],
        [2, '14:00', '15:00', 'Soutenance de stage : M. Lemoine', 'Salle B12'],
        [3, '09:00', '10:30', 'Point avec Claire', 'Visio'],
        [3, '14:00', '16:00', 'Jury de projet', 'Salle B12'],
        [4, '10:00', '11:00', 'Préparation du jury', 'Bureau scolarité'],
        [4, '10:30', '11:30', 'Appel de l’imprimeur', 'Téléphone'], // chevauche la précédente : démonstration des conflits
        [4, '14:00', '15:00', 'Réunion de département', 'Salle des profs'],
        [5, '09:00', '10:00', 'Permanence étudiants', 'Bureau 214'],
        [5, '16:30', '17:00', 'Point hebdomadaire de l’équipe', 'Visio'],
    ];
    private const PERSONAL = [
        [2, '18:30', '20:00', 'Badminton', 'Gymnase municipal'],
        [3, '08:00', '08:45', 'Rendez-vous médical', 'Cabinet du Dr Perrin'],
        [4, '19:00', '21:00', 'Dîner chez Marie', null],
    ];

    /** @return list<CalendarEventData> */
    public function events(string $url, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $plan = str_contains($url, 'perso') ? self::PERSONAL : self::WORK;
        $kind = str_contains($url, 'perso') ? 'perso' : 'iut';
        $tz = new \DateTimeZone('Europe/Paris');
        $monday = (new \DateTimeImmutable('monday this week', $tz))->setTime(0, 0);
        $out = [];
        for ($w = -2; $w <= 8; ++$w) {
            foreach ($plan as [$dow, $s, $e, $title, $place]) {
                $day = $monday->modify(sprintf('%+d weeks +%d days', $w, $dow - 1));
                $start = $day->modify($s);
                $end = $day->modify($e);
                if ($end > $from && $start < $to) {
                    $out[] = new CalendarEventData(sprintf('demo-%s-%s-%d@oscar', $kind, $day->format('Ymd'), $dow.crc32($title)), $title, $start, $end, $place);
                }
            }
        }
        if ($kind === 'iut') {
            // « Prochain RDV » vivant : deux rendez-vous aujourd'hui, à partir de la prochaine heure pleine.
            $now = new \DateTimeImmutable('now', $tz);
            $first = $now->setTime((int) $now->format('G') + 1, 0);
            if ((int) $first->format('G') < 19) {
                $out[] = new CalendarEventData('demo-iut-today-1@oscar', 'Point avec Sophie Laurent', $first, $first->modify('+45 minutes'), 'Visio');
                $out[] = new CalendarEventData('demo-iut-today-2@oscar', 'Relecture du planning des soutenances', $first->modify('+2 hours'), $first->modify('+3 hours'), 'Bureau 214');
            }
        }

        return $out;
    }
}
