<?php

namespace App\Ai;

/**
 * Fournisseur « IA de l'université » de base : règles déterministes en local, rien ne quitte le serveur.
 * Il remplit le contrat de l'interface sans modèle de langage ; un fournisseur Ollama ou cloud viendra derrière
 * la même interface. Le texte du mail n'est qu'analysé (expressions de dates, demandes), jamais interprété comme ordre.
 */
final class HeuristicProvider implements AiProvider
{
    private const DAYS = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7];
    private const REQUEST = '/\b(peux-tu|peux tu|pourrais-tu|pourrais-je|pouvez-vous|pourriez-vous|merci de|il faut|n[\'’]oublie pas|could you|can you|please)\b/iu';
    private const AVAILABILITY = '/\b(disponibilit[ée]s?|dispo|cr[ée]neaux?|libre|availability|available)\b/iu';

    public function __construct(private readonly FreeSlotFinder $slots = new FreeSlotFinder())
    {
    }

    public function name(): string
    {
        return 'university-heuristic';
    }

    public function analyse(string $subject, string $body, \DateTimeImmutable $mailDate, array $busy): MailAnalysis
    {
        $text = trim($body);
        $availability = preg_match(self::AVAILABILITY, $subject.' '.$text) === 1 && preg_match('/[?]|pourrais|peux|indiqu|donne|propos|please|could|can you/iu', $text) === 1;
        $slots = $availability ? $this->slots->find($busy, $this->weekStart($text, $mailDate), 5) : null;

        $event = $task = null;
        foreach ($this->sentences($text) as $sentence) {
            $event ??= $this->event($sentence, $subject, $mailDate);
            $task ??= $this->task($sentence, $mailDate);
        }
        $follow = !$availability && $task === null && preg_match(self::REQUEST, $text) === 1;

        return new MailAnalysis($slots === [] ? null : $slots, $event, $task, $follow);
    }

    public function draftRelance(string $contact, string $subject, string $me, string $locale): string
    {
        $first = explode(' ', trim($contact))[0] ?: $contact;

        return $locale === 'en'
            ? "Hello {$first},\n\nI'm following up on “{$subject}”. Could you take a moment to reply?\n\nThank you,\n{$me}"
            : "Bonjour {$first},\n\nJe me permets de revenir vers vous au sujet de « {$subject} ». Auriez-vous un moment pour me répondre ?\n\nMerci d’avance,\n{$me}";
    }

    /** @return list<string> */
    private function sentences(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+|\R+/u', $text) ?: []), static fn ($s) => mb_strlen($s) > 6));
    }

    /** « jeudi à 10h », « jeudi 10h30 » : un événement d'une heure, date calculée à vérifier. */
    private function event(string $s, string $subject, \DateTimeImmutable $mail): ?array
    {
        $days = implode('|', array_keys(self::DAYS));
        if (!preg_match('/\b('.$days.')\s*(?:prochain\s*)?(?:à|a|,)?\s*(\d{1,2})\s*(?:h|:)\s*(\d{2})?/iu', $s, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $hour = (int) $m[2][0];
        $min = isset($m[3]) ? (int) $m[3][0] : 0;
        if ($hour > 23 || $min > 59) {
            return null; // sortie invalide : ignorée, pas appliquée
        }
        $start = $this->nextWeekday($mail, self::DAYS[mb_strtolower($m[1][0])])->setTime($hour, $min);
        $before = trim(preg_replace('/^(?:bonjour[^,.]*[,.]?|merci[^,.]*[,.]?)\s*/iu', '', substr($s, 0, $m[0][1])) ?? '', " \t,.;:-");
        $title = $before !== '' && mb_strlen($before) >= 4 ? mb_substr($before, 0, 120) : mb_substr($subject, 0, 120);

        return ['title' => ucfirst($title), 'start' => $start->format(\DATE_ATOM), 'end' => $start->modify('+1 hour')->format(\DATE_ATOM), 'quote' => trim($m[0][0])];
    }

    /** « avant mardi » avec une demande : une tâche à échéance calculée. */
    private function task(string $s, \DateTimeImmutable $mail): ?array
    {
        $days = implode('|', array_keys(self::DAYS));
        if (!preg_match('/\b(avant|d[\'’]ici|pour|by)\s+(?:le\s+)?('.$days.')\b/iu', $s, $m) || preg_match(self::REQUEST, $s) !== 1) {
            return null;
        }
        $title = preg_replace('/\b(avant|d[\'’]ici|pour|by)\s+(?:le\s+)?('.$days.')\b.*$/iu', '', $s) ?? $s;
        $title = trim(preg_replace('/^(bonjour[^,.]*[,.]?\s*)?(peux-tu|peux tu|pourrais-tu|pouvez-vous|pourriez-vous|merci de|il faut|n[\'’]oublie pas|could you|can you|please)\s*/iu', '', $title) ?? $title, " \t,.;:?-");
        if (mb_strlen($title) < 4) {
            return null;
        }

        return ['title' => ucfirst(mb_substr($title, 0, 120)), 'dueAt' => $this->nextWeekday($mail, self::DAYS[mb_strtolower($m[2])])->format('Y-m-d'), 'quote' => trim($m[0])];
    }

    /** Première occurrence du jour STRICTEMENT après la date du mail (« avant mardi » écrit un lundi = mardi). */
    private function nextWeekday(\DateTimeImmutable $from, int $isoDay): \DateTimeImmutable
    {
        $d = $from->setTime(0, 0)->modify('+1 day');
        while ((int) $d->format('N') !== $isoDay) {
            $d = $d->modify('+1 day');
        }

        return $d;
    }

    /** Début de la fenêtre de recherche : « semaine du 30 novembre » si dite, sinon lundi suivant le mail. */
    private function weekStart(string $text, \DateTimeImmutable $mail): \DateTimeImmutable
    {
        $months = ['janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12];
        if (preg_match('/semaine du (\d{1,2})(?:er)?\s+('.implode('|', array_keys($months)).')/iu', $text, $m)) {
            $d = $mail->setDate((int) $mail->format('Y'), $months[mb_strtolower($m[2])], (int) $m[1])->setTime(0, 0);
            if ($d < $mail->setTime(0, 0)) {
                $d = $d->modify('+1 year');
            }

            return $d;
        }

        return $this->nextWeekday($mail, 1);
    }
}
