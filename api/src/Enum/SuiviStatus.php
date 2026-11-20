<?php

namespace App\Enum;

/**
 * États d'un suivi (F-51). « En retard » et « relance due » ne sont PAS des états :
 * ce sont des signaux calculés (échéance, date de relance), jamais stockés.
 */
enum SuiviStatus: string
{
    case New = 'new';
    case InProgress = 'inProgress';
    case Waiting = 'waiting';
    case Done = 'done';
}
