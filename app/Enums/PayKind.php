<?php

namespace App\Enums;

enum PayKind: string
{
    case Fixed = 'fixed';
    case Hourly = 'hourly';
}
