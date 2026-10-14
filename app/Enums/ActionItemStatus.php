<?php

namespace App\Enums;

/** Allowed transitions: see PRD §6. "Overdue" is computed, not a status. */
enum ActionItemStatus: string
{
    case TODO = 'TODO';
    case IN_PROGRESS = 'IN_PROGRESS';
    case DONE = 'DONE';
}
