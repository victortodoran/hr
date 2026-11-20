<?php

namespace App\Pages\Travel;

use App\Models\CompanySettings;
use App\Models\Employee;
use App\Support\TravelHistory;

final class TravelPage
{
    /** @return array<string, mixed> */
    public static function props(Employee $actor): array
    {
        return [
            'travelRequests' => TravelHistory::of($actor, CompanySettings::payCurrency()),
        ];
    }
}
