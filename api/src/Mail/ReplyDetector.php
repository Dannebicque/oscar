<?php

namespace App\Mail;

/**
 * Détection de la réponse par les en-têtes du fil (F-52), jamais par l'objet.
 * Une réponse automatique (absence) ne compte pas ; la réponse de n'importe quel destinataire compte.
 * La détection ne clôt rien : elle signale (F-65).
 */
final class ReplyDetector
{
    public function isReply(MailHeader $candidate, string $threadMessageId): bool
    {
        if ($candidate->autoSubmitted) {
            return false;
        }

        return \in_array(MailHeader::normalizeId($threadMessageId), $candidate->parents(), true);
    }

    /**
     * @param iterable<MailHeader> $candidates
     */
    public function firstReply(iterable $candidates, string $threadMessageId): ?MailHeader
    {
        $found = null;
        foreach ($candidates as $c) {
            if ($this->isReply($c, $threadMessageId) && ($found === null || $c->date < $found->date)) {
                $found = $c;
            }
        }

        return $found;
    }

    /** Valeur brute d'un en-tête Auto-Submitted / Precedence → réponse automatique ? */
    public static function looksAutomatic(?string $autoSubmitted, ?string $precedence = null, ?string $autoResponseSuppress = null): bool
    {
        if ($autoSubmitted !== null && strtolower(trim($autoSubmitted)) !== 'no' && trim($autoSubmitted) !== '') {
            return true;
        }
        if ($precedence !== null && \in_array(strtolower(trim($precedence)), ['bulk', 'auto_reply', 'junk', 'list'], true)) {
            return true;
        }

        return $autoResponseSuppress !== null && trim($autoResponseSuppress) !== '';
    }
}
