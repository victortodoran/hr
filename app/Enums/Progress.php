<?php

namespace App\Enums;

enum Progress: string
{
    case NotStarted = 'not-started';
    case OnTrack = 'on-track';
    case AtRisk = 'at-risk';
    case Done = 'done';
}
