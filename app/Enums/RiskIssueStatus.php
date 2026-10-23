<?php

namespace App\Enums;

/** Allowed transitions: OPEN → IN_PROGRESS → CLOSED, or OPEN → CLOSED (assumption, pending Q-09). */
enum RiskIssueStatus: string
{
    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case CLOSED = 'CLOSED';
}
