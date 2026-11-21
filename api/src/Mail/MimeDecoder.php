<?php

namespace App\Mail;

/**
 * Décode les « encoded-words » RFC 2047 d'un en-tête (=?utf-8?Q?R=C3=A9cap?= → Récap).
 * Idempotent : un texte déjà décodé ressort inchangé, un jeu de caractères inconnu laisse le texte tel quel.
 */
final class MimeDecoder
{
    public static function decode(string $value): string
    {
        // Seulement s'il y a un vrai mot encodé : un « =? » isolé ne doit jamais abîmer un objet.
        if (preg_match('/=\?[^?\s]+\?[BbQq]\?[^?\s]*\?=/', $value) !== 1) {
            return $value;
        }
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false || $decoded === '' ? $value : $decoded;
    }
}
