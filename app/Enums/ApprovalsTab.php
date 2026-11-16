<?php

namespace App\Enums;

/**
 * The tabs of the Approvals page: Pending Work Entries to approve, and Time
 * Off, Travel Requests and Travel Expenses to approve or decline.
 */
enum ApprovalsTab: string
{
    case WorkEntries = 'work-entries';
    case TimeOff = 'time-off';
    case TravelRequests = 'travel-requests';
    case TravelExpenses = 'travel-expenses';
}
