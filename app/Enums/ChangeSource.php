<?php

namespace App\Enums;

enum ChangeSource: string
{
    case USER = 'USER';
    case INTERNAL = 'INTERNAL';
}