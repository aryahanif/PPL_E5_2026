<?php

namespace App\Enums;

/** Allowed transitions: see PRD §6. */
enum MeetingStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case DONE = 'DONE';
    case CANCELLED = 'CANCELLED';
}
