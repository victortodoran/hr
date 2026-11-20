<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\TravelExpenseState;
use App\Enums\TravelRequestState;
use App\Models\Builders\TravelExpenseBuilder;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;

final class TravelHistory
{
    /**
     * Past also holds Declined and Cancelled Travel Requests whose dates are still ahead.
     *
     * @return array{upcoming: array<int, array{id: int, destination: string, first_date: string, last_date: string, project: string, client: string|null, state: string, takes_expenses: bool, entered_total: string|null, approved_total: string|null, declined_expenses: int}>, past: array<int, array{id: int, destination: string, first_date: string, last_date: string, project: string, client: string|null, state: string, takes_expenses: bool, entered_total: string|null, approved_total: string|null, declined_expenses: int}>}
     */
    public static function of(Employee $employee, Currency $currency): array
    {
        $travelRequests = $employee->travelRequests()
            ->with('assignment.project.client:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->withSum(['travelExpenses as entered_amount' => fn (TravelExpenseBuilder $expenses) => $expenses->notDeclined()], 'amount')
            ->withSum(['travelExpenses as approved_amount' => fn (TravelExpenseBuilder $expenses) => $expenses->approved()], 'amount')
            ->withCount(['travelExpenses as declined_expenses' => fn (TravelExpenseBuilder $expenses) => $expenses->declined()])
            ->orderBy('first_date')
            ->orderBy('id')
            ->get();
        $isUpcoming = fn (TravelRequest $travelRequest): bool => in_array($travelRequest->state, TravelRequestState::standing(), true)
            && ! $travelRequest->last_date->isBefore(today());

        $row = fn (TravelRequest $travelRequest): array => [
            'id' => $travelRequest->id,
            'destination' => $travelRequest->destination,
            'first_date' => $travelRequest->first_date->toDateString(),
            'last_date' => $travelRequest->last_date->toDateString(),
            'project' => $travelRequest->assignment->project->name,
            'client' => $travelRequest->assignment->project->client?->name,
            'state' => $travelRequest->state->value,
            'takes_expenses' => $travelRequest->takesExpenses(),
            'entered_total' => $travelRequest->takesExpenses() ? $currency->format((int) $travelRequest->getAttribute('entered_amount')) : null,
            'approved_total' => $travelRequest->takesExpenses() ? $currency->format((int) $travelRequest->getAttribute('approved_amount')) : null,
            'declined_expenses' => (int) $travelRequest->getAttribute('declined_expenses'),
        ];

        return [
            'upcoming' => $travelRequests->filter($isUpcoming)->map($row)->values()->all(),
            'past' => $travelRequests->reject($isUpcoming)->reverse()->map($row)->values()->all(),
        ];
    }

    /** @return array{id: int, project: string, client: string|null, destination: string, first_date: string, last_date: string, days: int, purpose: string, state: string, decided_by: string|null, decline_reason: string|null, can_cancel: bool, can_decide: bool, expenses: array{items: array<int, array{id: int, paid_on: string, category: string, description: string, amount: string, state: string, decline_reason: string|null, declined_by: string|null, receipt_is_image: bool, can_change: bool, can_decide: bool, can_reopen: bool}>, total: string, approved_total: string, pending_total: string, declined_total: string, can_create: bool}|null} */
    public static function detail(TravelRequest $travelRequest, Currency $currency, Employee $actor): array
    {
        $travelRequest->loadMissing(['assignment.project.client:id,name', 'decider:id,name']); // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)

        return [
            'id' => $travelRequest->id,
            'project' => $travelRequest->assignment->project->name,
            'client' => $travelRequest->assignment->project->client?->name,
            'destination' => $travelRequest->destination,
            'first_date' => $travelRequest->first_date->toDateString(),
            'last_date' => $travelRequest->last_date->toDateString(),
            'days' => $travelRequest->days(),
            'purpose' => $travelRequest->purpose,
            'state' => $travelRequest->state->value,
            'decided_by' => $travelRequest->decider?->name,
            'decline_reason' => $travelRequest->decline_reason,
            'can_cancel' => $actor->can('cancel', $travelRequest),
            'can_decide' => $actor->can('decide', $travelRequest),
            'expenses' => $travelRequest->takesExpenses() ? self::expenses($travelRequest, $currency, $actor) : null,
        ];
    }

    /**
     * Deciding and reopening are worked out once per Travel Request, as TravelExpensePolicy::decide has it, instead of per row.
     *
     * @return array{items: array<int, array{id: int, paid_on: string, category: string, description: string, amount: string, state: string, decline_reason: string|null, declined_by: string|null, receipt_is_image: bool, can_change: bool, can_decide: bool, can_reopen: bool}>, total: string, approved_total: string, pending_total: string, declined_total: string, can_create: bool}
     */
    private static function expenses(TravelRequest $travelRequest, Currency $currency, Employee $actor): array
    {
        $travelExpenses = $travelRequest->travelExpenses()->with('decliner:id,name')->orderBy('paid_on')->orderBy('id')->get(); // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
        $reopensForEmployee = Employee::query()->reopenableBy($actor, Permission::ReopenTravelExpenses)->whereKey($travelRequest->employee_id)->exists();
        $decidesForEmployee = Employee::query()->effectivelyManagedBy($actor)->whereKey($travelRequest->employee_id)->exists();
        $total = fn (TravelExpenseState $state): string => $currency->format((int) $travelExpenses
            ->filter(fn (TravelExpense $travelExpense): bool => $travelExpense->state === $state)
            ->sum('amount'));

        return [
            'items' => $travelExpenses
                ->map(function (TravelExpense $travelExpense) use ($travelRequest, $currency, $actor, $decidesForEmployee, $reopensForEmployee): array {
                    $travelExpense->setRelation('travelRequest', $travelRequest);

                    return [
                        'id' => $travelExpense->id,
                        'paid_on' => $travelExpense->paid_on->toDateString(),
                        'category' => $travelExpense->category->value,
                        'description' => $travelExpense->description,
                        'amount' => $currency->format($travelExpense->amount),
                        'state' => $travelExpense->state->value,
                        'decline_reason' => $travelExpense->decline_reason,
                        'declined_by' => $travelExpense->decliner?->name,
                        'receipt_is_image' => $travelExpense->receiptIsImage(),
                        'can_change' => $actor->can('update', $travelExpense),
                        'can_decide' => $decidesForEmployee && $travelExpense->state === TravelExpenseState::Pending,
                        'can_reopen' => $reopensForEmployee && $travelExpense->state === TravelExpenseState::Approved,
                    ];
                })
                ->all(),
            'total' => $currency->format((int) $travelExpenses
                ->reject(fn (TravelExpense $travelExpense): bool => $travelExpense->state === TravelExpenseState::Declined)
                ->sum('amount')),
            'approved_total' => $total(TravelExpenseState::Approved),
            'pending_total' => $total(TravelExpenseState::Pending),
            'declined_total' => $total(TravelExpenseState::Declined),
            'can_create' => $actor->can('create', [TravelExpense::class, $travelRequest]),
        ];
    }
}
