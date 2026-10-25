<?php

namespace App\Enums;

/** Safe/late indicator of a project (BL-12). Computed on request, never stored. */
enum ProjectIndicator: string
{
    case ON_TRACK = 'ON_TRACK';
    case DELAYED = 'DELAYED';
}
