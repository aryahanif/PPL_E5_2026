<?php

namespace App\Enums;

/** Allowed transitions: see PRD §6. */
enum MomStatus: string
{
    case DRAFT = 'DRAFT';
    case IN_REVIEW = 'IN_REVIEW';
    case REVISION = 'REVISION';
    case FINAL = 'FINAL';
}
