<?php

namespace App\Pages\TimeOff;

use App\Models\Employee;

/**
 * The Time Off page with the sheet for requesting Time Off open over it
 * (rule L14), as the props it renders.
 *
 * @phpstan-import-type PaidDaysLeftInYear from \App\Support\PaidDaysLeft
 */
final class TimeOffFormPage
{
    /**
     * The Time Off page's props (TimeOffPage), that the sheet is open, and
     * today, the earliest first date.
     *
     * @return array{manager: string|null, paidDaysLeft: list<PaidDaysLeftInYear>, timeOff: array<int, array<string, mixed>>, requesting: true, today: string}
     */
    public static function create(Employee $actor): array
    {
        return [
            ...TimeOffPage::props($actor),
            'requesting' => true,
            'today' => today()->toDateString(),
        ];
    }
}
