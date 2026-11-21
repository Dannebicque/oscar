<?php

namespace App\Calendar;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * Invitations iCalendar reçues par mail (F-25, iTIP). Le texte est une DONNÉE non fiable : on n'en lit que ce qui sert à l'affichage
 * (titre, dates, lieu, organisateur), nettoyé et tronqué ; on n'exécute rien, on ne suit aucun lien.
 */
final class InvitationParser
{
    /**
     * @return ?array{uid: string, title: string, start: string, end: string, location: ?string, organizerName: string, organizerAddress: string, sequence: int}
     */
    public function parse(string $ics): ?array
    {
        try {
            $cal = Reader::read($ics, Reader::OPTION_FORGIVING);
            if (!$cal instanceof VCalendar || strtoupper((string) ($cal->METHOD ?? '')) !== 'REQUEST' || !isset($cal->VEVENT)) {
                return null;
            }
            $ev = $cal->VEVENT;
            if (!isset($ev->DTSTART, $ev->UID)) {
                return null;
            }
            $start = \DateTimeImmutable::createFromInterface($ev->DTSTART->getDateTime());
            $end = isset($ev->DTEND) ? \DateTimeImmutable::createFromInterface($ev->DTEND->getDateTime()) : (isset($ev->DURATION) ? $start->add($ev->DURATION->getDateInterval()) : $start->modify('+1 hour'));
            if ($end <= $start || $end > $start->modify('+30 days')) {
                return null;
            }
            $org = isset($ev->ORGANIZER) ? (string) $ev->ORGANIZER : '';
            $address = strtolower(preg_replace('/^mailto:/i', '', $org) ?? '');
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return null; // sans organisateur joignable, on ne peut pas répondre
            }
            $name = isset($ev->ORGANIZER['CN']) ? (string) $ev->ORGANIZER['CN'] : $address;

            return [
                'uid' => mb_substr((string) $ev->UID, 0, 255),
                'title' => $this->text((string) ($ev->SUMMARY ?? ''), 160) ?? '(sans titre)',
                'start' => $start->format(\DATE_ATOM),
                'end' => $end->format(\DATE_ATOM),
                'location' => $this->text((string) ($ev->LOCATION ?? ''), 160),
                'organizerName' => $this->text($name, 120) ?? $address,
                'organizerAddress' => $address,
                'sequence' => isset($ev->SEQUENCE) ? (int) $ev->SEQUENCE->getValue() : 0,
            ];
        } catch (\Throwable) {
            return null; // texte illisible : pas d'invitation
        }
    }

    /** Réponse iTIP (METHOD:REPLY) à renvoyer à l'organisateur. $partstat : ACCEPTED | DECLINED | TENTATIVE. */
    public function reply(array $inv, string $attendeeAddress, string $attendeeName, string $partstat): string
    {
        $cal = new VCalendar();
        $cal->PRODID = '-//Oscar//FR';
        $cal->METHOD = 'REPLY';
        $cal->add('VEVENT', [
            'UID' => $inv['uid'], 'SEQUENCE' => $inv['sequence'], 'DTSTAMP' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            'DTSTART' => new \DateTimeImmutable($inv['start']), 'DTEND' => new \DateTimeImmutable($inv['end']), 'SUMMARY' => $inv['title'],
        ]);
        $cal->VEVENT->add('ORGANIZER', 'mailto:'.$inv['organizerAddress'], ['CN' => $inv['organizerName']]);
        $cal->VEVENT->add('ATTENDEE', 'mailto:'.$attendeeAddress, ['CN' => $attendeeName, 'PARTSTAT' => $partstat]);

        return $cal->serialize();
    }

    private function text(string $v, int $max): ?string
    {
        $v = trim(preg_replace('/\s+/', ' ', strip_tags($v)) ?? '');

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
