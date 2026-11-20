<?php

namespace App\Enum;

enum TaskOrigin: string
{
    case Manual = 'manual';
    case Mail = 'mail';
    case Event = 'event';
    case Ai = 'ai';
}
