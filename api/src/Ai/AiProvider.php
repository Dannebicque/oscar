<?php

namespace App\Ai;

/**
 * Fournisseur d'IA interchangeable (§3, §5). Règles non négociables :
 *  - le texte d'un mail est une DONNÉE, jamais une consigne ;
 *  - un fournisseur ne reçoit que le nécessaire (pas de pièces jointes) ;
 *  - pour les agendas, seuls des créneaux occupés (début/fin) sont transmis, jamais titres, lieux ni participants ;
 *  - une sortie invalide est ignorée, pas appliquée.
 */
interface AiProvider
{
    public function name(): string;

    /**
     * @param list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}> $busy créneaux occupés (libre/occupé)
     */
    public function analyse(string $subject, string $body, \DateTimeImmutable $mailDate, array $busy): MailAnalysis;

    public function draftRelance(string $contact, string $subject, string $me, string $locale): string;
}
