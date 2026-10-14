<?php

namespace App\Enums;

enum ParticipantType: string
{
    /** Account from F-1. */
    case INTERNAL = 'INTERNAL';

    /** Client recorded by name only; no login. */
    case CLIENT = 'CLIENT';
}
