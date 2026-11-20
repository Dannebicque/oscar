<?php

namespace App\Recap;

/** Texte du récap par e-mail, en français ou en anglais (N-10). Ton d'Oscar : court, jamais de reproche. */
final class RecapText
{
    private const T = [
        'fr' => [
            'morning' => 'Récap du matin', 'evening' => 'Bilan du soir', 'intro' => 'Voilà ce qui compte maintenant. Je prépare, tu décides.',
            'mails' => 'Mails importants non traités', 'events' => 'Événements à venir', 'tasks' => 'Tâches du jour et en retard', 'relances' => 'Relances dues',
            'nothing' => 'Rien à signaler.', 'late' => 'en retard', 'replyReceived' => 'réponse reçue', 'outro' => 'Aucune relance ne part sans ton clic.',
        ],
        'en' => [
            'morning' => 'Morning recap', 'evening' => 'Evening review', 'intro' => 'Here is what matters now. I prepare, you decide.',
            'mails' => 'Important unhandled mails', 'events' => 'Upcoming events', 'tasks' => 'Tasks today and overdue', 'relances' => 'Reminders due',
            'nothing' => 'Nothing to report.', 'late' => 'overdue', 'replyReceived' => 'reply received', 'outro' => 'No reminder is sent without your click.',
        ],
    ];

    public function subject(string $slot, string $locale, \DateTimeImmutable $day): string
    {
        return sprintf('%s · %s', self::T[$locale][$slot] ?? self::T['fr'][$slot], $day->format('Y-m-d'));
    }

    /** @param array{mails: list<array>, events: list<array>, tasks: list<array>, relances: list<array>} $c */
    public function body(string $slot, array $c, string $locale): string
    {
        $t = self::T[$locale] ?? self::T['fr'];
        $sections = [
            'mails' => array_map(static fn ($m) => "{$m['subject']} ({$m['contact']}, ".($t[$m['reason']] ?? '').')', $c['mails']),
            'events' => array_map(static fn ($e) => substr($e['start'], 11, 5).' '.$e['title'], $c['events']),
            'tasks' => array_map(static fn ($x) => $x['title'], $c['tasks']),
            'relances' => array_map(static fn ($r) => "{$r['subject']} ({$r['contact']})", $c['relances']),
        ];
        $out = [$t[$slot], $t['intro'], ''];
        foreach ($sections as $key => $lines) {
            $out[] = $t[$key];
            array_push($out, ...($lines === [] ? ['  - '.$t['nothing']] : array_map(static fn ($l) => '  - '.$l, $lines)));
            $out[] = '';
        }
        $out[] = $t['outro'];

        return implode("\n", $out);
    }
}
