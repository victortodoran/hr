<?php

namespace App\Enums;

enum TravelExpenseCategory: string
{
    case Transport = 'transport';
    case Accommodation = 'accommodation';
    case Meals = 'meals';
    case Other = 'other';
}
