<?php

namespace App\Enum;

enum SuiviState: string
{
    case ToHandle = 'toHandle';
    case RelanceDue = 'relanceDue';
    case Late = 'late';
    case Waiting = 'waiting';
    case Done = 'done';
}
