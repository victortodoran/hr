<?php

namespace App\Enums;

/**
 * How far along a Goal is during the year, from a fixed list the HR
 * system defines. Every Goal starts Not Started. Done means the work is
 * finished, not how well it went: that is the Outcome.
 */
enum Progress: string
{
    case NotStarted = 'not-started';
    case OnTrack = 'on-track';
    case AtRisk = 'at-risk';
    case Done = 'done';
}
