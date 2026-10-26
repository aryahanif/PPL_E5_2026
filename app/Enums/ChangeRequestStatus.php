<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case RECORDED = 'RECORDED';
    case PENDING_ANALYSIS = 'PENDING_ANALYSIS';
    case ANALYZED = 'ANALYZED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}