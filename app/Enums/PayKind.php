<?php

namespace App\Enums;

/**
 * The two kinds of Pay: a set amount each month, or for each hour worked.
 */
enum PayKind: string
{
    case Fixed = 'fixed';
    case Hourly = 'hourly';
}
