<?php

namespace App\Pages\Travel;

use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Support\TravelHistory;

final class TravelRequestPage
{
    /** @return array<string, mixed> */
    public static function props(TravelRequest $travelRequest, Employee $actor): array
    {
        return [
            'travelRequest' => TravelHistory::detail($travelRequest, CompanySettings::payCurrency(), $actor),
            'employee' => $travelRequest->employee->only('id', 'name'),
            'isOwn' => $travelRequest->employee_id === $actor->id,
            'canViewEmployee' => $actor->can('view', $travelRequest->employee),
            'canViewTravelRequests' => $actor->can('viewList', TravelRequest::class),
        ];
    }
}
