<?php

namespace App\Enums;

enum ChangeType: string
{
    case SCOPE = 'SCOPE';
    case TIMELINE = 'TIMELINE';
    case TASK = 'TASK';
    case RESOURCE = 'RESOURCE';
}