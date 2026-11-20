<?php

namespace App\Calendar;

use App\Entity\CalendarAccount;
use App\Security\CredentialVault;
use Sabre\VObject\Reader;
use Sabre\VObject\Component\VCalendar;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Client CalDAV minimal (REPORT calendar-query, PUT) avec sabre/vobject pour iCalendar. */
final class CalDavProvider implements CalendarProvider
{
    public function __construct(private readonly HttpClientInterface $http, private readonly CredentialVault $vault)
    {
    }

    public function events(CalendarAccount $account, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $body = sprintf(
            '<?xml version="1.0" encoding="utf-8"?><c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:prop><c:calendar-data/></d:prop><c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT"><c:time-range start="%s" end="%s"/></c:comp-filter></c:comp-filter></c:filter></c:calendar-query>',
            $from->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            $to->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
        );
        $res = $this->http->request('REPORT', $account->getUrl(), $this->options($account) + ['headers' => ['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'], 'body' => $body]);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf('Le serveur d’agenda a répondu %d.', $res->getStatusCode()));
        }

        return $this->parse($res->getContent(), $from, $to);
    }

    public function create(CalendarAccount $account, CalendarEventData $e): void
    {
        $cal = new VCalendar();
        $cal->add('VEVENT', [
            'UID' => $e->uid, 'SUMMARY' => $e->title, 'DTSTART' => $e->start->setTimezone(new \DateTimeZone('UTC')), 'DTEND' => $e->end->setTimezone(new \DateTimeZone('UTC')),
            'DTSTAMP' => new \DateTimeImmutable(),
        ] + ($e->location !== null ? ['LOCATION' => $e->location] : []));
        $url = rtrim($account->getUrl(), '/').'/'.rawurlencode($e->uid).'.ics';
        $res = $this->http->request('PUT', $url, $this->options($account) + ['headers' => ['Content-Type' => 'text/calendar; charset=utf-8', 'If-None-Match' => '*'], 'body' => $cal->serialize()]);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf('Le serveur d’agenda a refusé l’événement (%d).', $res->getStatusCode()));
        }
    }

    /** @return list<CalendarEventData> */
    public function parse(string $multistatus, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $xml = @simplexml_load_string($multistatus, options: LIBXML_NONET);
        if ($xml === false) {
            throw new \RuntimeException('Réponse du serveur d’agenda illisible.');
        }
        $out = [];
        foreach ($xml->xpath('//*[local-name()="calendar-data"]') ?: [] as $node) {
            try {
                $vcal = Reader::read((string) $node);
            } catch (\Throwable) {
                continue; // un événement illisible n'empêche pas les autres
            }
            if (!$vcal instanceof VCalendar) {
                continue;
            }
            $expanded = isset($vcal->VEVENT) && isset($vcal->VEVENT->RRULE) ? $vcal->expand(\DateTime::createFromImmutable($from), \DateTime::createFromImmutable($to)) : $vcal;
            foreach ($expanded->VEVENT ?? [] as $ev) {
                if (!isset($ev->DTSTART)) {
                    continue;
                }
                $start = \DateTimeImmutable::createFromInterface($ev->DTSTART->getDateTime());
                $end = isset($ev->DTEND) ? \DateTimeImmutable::createFromInterface($ev->DTEND->getDateTime()) : (isset($ev->DURATION) ? $start->add($ev->DURATION->getDateInterval()) : $start->modify('+1 hour'));
                $uid = (string) ($ev->UID ?? '').(isset($ev->{'RECURRENCE-ID'}) ? '#'.$start->format('c') : '');
                if ($uid === '' || $end <= $from || $start >= $to) {
                    continue;
                }
                $out[] = new CalendarEventData($uid, trim((string) ($ev->SUMMARY ?? '')) ?: '(sans titre)', $start, $end, isset($ev->LOCATION) && trim((string) $ev->LOCATION) !== '' ? trim((string) $ev->LOCATION) : null);
            }
        }

        return $out;
    }

    private function options(CalendarAccount $a): array
    {
        return $a->getUsername() === '' ? [] : ['auth_basic' => [$a->getUsername(), $this->vault->decrypt($a->getPasswordEncrypted())]];
    }
}
