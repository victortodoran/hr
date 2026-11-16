<?php

namespace App\Enums;

/**
 * The tabs of the Approvals page: Pending Work Entries to approve, and Time
 * Off and Travel Requests to approve or decline.
 */
enum ApprovalsTab: string
{
    case WorkEntries = 'work-entries';
    case TimeOff = 'time-off';
    case TravelRequests = 'travel-requests';
}
