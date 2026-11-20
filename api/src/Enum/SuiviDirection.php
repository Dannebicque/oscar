<?php

namespace App\Enum;

/** Sens du suivi (F-50) : un mail reçu appelle une action, ou un mail envoyé attend une réponse. */
enum SuiviDirection: string
{
    case ToHandle = 'toHandle';
    case Waiting = 'waiting';
}
