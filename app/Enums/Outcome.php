<?php

namespace App\Enums;

/**
 * How well a Goal, or a Performance Evaluation as a whole, was achieved,
 * from a fixed list the HR system defines.
 */
enum Outcome: string
{
    case NotMet = 'not-met';
    case PartlyMet = 'partly-met';
    case Met = 'met';
    case Exceeded = 'exceeded';
}
