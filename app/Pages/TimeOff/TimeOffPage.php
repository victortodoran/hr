<?php

namespace App\Pages\TimeOff;

use App\Models\Employee;
use App\Models\TimeOff;
use App\Support\PaidDaysLeft;

/**
 * The Time Off page as the props it renders: the signed-in Employee's own
 * Time Off and their Paid Days left.
 *
 * @phpstan-import-type PaidDaysLeftInYear from PaidDaysLeft
 */
final class TimeOffPage
{
    /**
     * The Employee's Time Off, latest first, with the weekdays each counts,
     * who decided it and why it was declined, and whether they may still
     * cancel it, their Paid Days left this year and, from October, next
     * year, and the name of their effective Manager, who decides it, or null
     * when every Manager up the chain has left and requests are Approved at once.
     *
     * @return array{manager: string|null, paidDaysLeft: list<PaidDaysLeftInYear>, timeOff: array<int, array<string, mixed>>}
     */
    public static function props(Employee $actor): array
    {
        return [
            'manager' => Employee::query()->withEffectiveManager()->with('effectiveManager:id,name')->findOrFail($actor->id)->effectiveManager?->name,
            'paidDaysLeft' => PaidDaysLeft::of($actor),
            'timeOff' => $actor->timeOff()
                ->with('decider:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderByDesc('first_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (TimeOff $timeOff): array => [
                    ...$timeOff->details(),
                    'can_cancel' => $actor->can('cancel', $timeOff),
                ])
                ->all(),
        ];
    }
}
