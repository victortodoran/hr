<?php

namespace App\Enums;

enum Outcome: string
{
    case NotMet = 'not-met';
    case PartlyMet = 'partly-met';
    case Met = 'met';
    case Exceeded = 'exceeded';
}
