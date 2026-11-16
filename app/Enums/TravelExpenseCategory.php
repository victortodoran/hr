<?php

namespace App\Enums;

/**
 * What a Travel Expense paid for, fixed by the HR system.
 */
enum TravelExpenseCategory: string
{
    case Transport = 'transport';
    case Accommodation = 'accommodation';
    case Meals = 'meals';
    case Other = 'other';
}
