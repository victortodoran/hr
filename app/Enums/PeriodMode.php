<?php

namespace App\Enums;

enum PeriodMode: string
{
    case Week = 'week';
    case Month = 'month';
    case Custom = 'custom';
}
