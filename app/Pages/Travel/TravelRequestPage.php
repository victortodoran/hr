<?php

namespace App\Pages\Travel;

use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Support\TravelHistory;

/**
 * One Travel Request's page as the props it renders.
 */
final class TravelRequestPage
{
    /**
     * The Travel Request in full with its Travel Expenses, and what the
     * viewer may do to them, which only the Employee themselves ever may.
     * Whether it is the viewer's own sets where its breadcrumbs lead: their
     * Travel page, or the Employee's record when the viewer may open it.
     *
     * @return array<string, mixed>
     */
    public static function props(TravelRequest $travelRequest, Employee $actor): array
    {
        return [
            'travelRequest' => TravelHistory::detail($travelRequest, CompanySettings::payCurrency(), $actor),
            'employee' => $travelRequest->employee->only('id', 'name'),
            'isOwn' => $travelRequest->employee_id === $actor->id,
            'canViewEmployee' => $actor->can('view', $travelRequest->employee),
        ];
    }
}
