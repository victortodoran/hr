<?php

namespace App\Enums;

enum ApprovalsTab: string
{
    case WorkEntries = 'work-entries';
    case TimeOff = 'time-off';
    case TravelRequests = 'travel-requests';
    case TravelExpenses = 'travel-expenses';
}
