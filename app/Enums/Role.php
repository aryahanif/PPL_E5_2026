<?php

namespace App\Enums;

/** Roles issued by F-1 that E-5 uses (PRD §3). */
enum Role: string
{
    case PM = 'PM';
    case TL = 'TL';
    case TW = 'TW';
}
