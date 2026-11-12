<?php

namespace App\Enums;

/**
 * The tabs of the Approvals page: Work Entries to approve, reopen or lock,
 * and Time Off to approve or decline.
 */
enum ApprovalsTab: string
{
    case WorkEntries = 'work-entries';
    case TimeOff = 'time-off';
}
