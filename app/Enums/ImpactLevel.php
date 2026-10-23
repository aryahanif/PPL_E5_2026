<?php

namespace App\Enums;

/** Optional on a risk/issue (assumption, pending Q-08). */
enum ImpactLevel: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
}
