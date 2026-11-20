<?php

namespace App\Ai;

/** Cherche des créneaux libres d'une heure à partir des seuls créneaux occupés (libre/occupé, §5). */
final class FreeSlotFinder
{
    private const HOURS = [9, 10, 11, 14, 15, 16];

    /**
     * @param list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}> $busy
     *
     * @return list<array{start: string, end: string}>
     */
    public function find(array $busy, \DateTimeImmutable $from, int $count = 5, int $days = 14): array
    {
        $out = [];
        $perDay = [];
        $tz = $from->getTimezone();
        for ($d = 0; $d < $days && \count($out) < $count; ++$d) {
            $day = $from->setTime(0, 0)->modify("+$d days");
            if ((int) $day->format('N') >= 6) {
                continue; // week-end
            }
            foreach (self::HOURS as $h) {
                $start = $day->setTime($h, 0);
                $end = $start->modify('+1 hour');
                if ($start < $from || ($perDay[$day->format('Y-m-d')] ?? 0) >= 1 || $this->overlaps($busy, $start, $end)) {
                    continue;
                }
                $out[] = ['start' => $start->format(\DATE_ATOM), 'end' => $end->format(\DATE_ATOM)];
                $perDay[$day->format('Y-m-d')] = 1; // un créneau par jour : on propose de la variété
                if (\count($out) >= $count) {
                    break;
                }
            }
        }
        unset($tz);

        return $out;
    }

    /** @param list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}> $busy */
    private function overlaps(array $busy, \DateTimeImmutable $s, \DateTimeImmutable $e): bool
    {
        foreach ($busy as $b) {
            if ($b['start'] < $e && $b['end'] > $s) {
                return true;
            }
        }

        return false;
    }
}
