<?php

namespace App\Pages\TimeOff;

use App\Models\Employee;

/** @phpstan-import-type PaidDaysLeftInYear from \App\Support\PaidDaysLeft */
final class TimeOffFormPage
{
    /** @return array{manager: string|null, paidDaysLeft: list<PaidDaysLeftInYear>, timeOff: array<int, array<string, mixed>>, requesting: true, today: string} */
    public static function create(Employee $actor): array
    {
        return [
            ...TimeOffPage::props($actor),
            'requesting' => true,
            'today' => today()->toDateString(),
        ];
    }
}
