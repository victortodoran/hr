<?php

namespace App\Pages\Travel;

use App\Models\CompanySettings;
use App\Models\Employee;
use App\Support\TravelHistory;

/**
 * The Travel page as the props it renders: the signed-in Employee's own
 * Travel Requests.
 */
final class TravelPage
{
    /**
     * The Employee's Travel Requests, Upcoming and Past (TravelHistory).
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor): array
    {
        return [
            'travelRequests' => TravelHistory::of($actor, CompanySettings::payCurrency()),
        ];
    }
}
