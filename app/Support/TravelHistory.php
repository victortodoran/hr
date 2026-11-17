<?php

namespace App\Support;

use App\Enums\TravelExpenseState;
use App\Enums\TravelRequestState;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * An Employee's Travel Requests as the Travel page and the Travel tab of the
 * Employee page both list them, split into Upcoming and Past, and one Travel
 * Request in full, with its Travel Expenses, as its own page shows it.
 */
final class TravelHistory
{
    /**
     * The Employee's Travel Requests as summary rows. Upcoming holds those
     * Pending or Approved whose last date is today or later, soonest first;
     * Past holds every other, newest first, Declined and Cancelled ones with
     * dates still ahead included. Each row carries the total of its Travel
     * Expenses not Declined, absent when it doesn't take Travel Expenses, and
     * how many are Declined.
     *
     * @return array{upcoming: array<int, array{id: int, destination: string, first_date: string, last_date: string, client: string|null, state: string, expense_total: string|null, declined_expenses: int}>, past: array<int, array{id: int, destination: string, first_date: string, last_date: string, client: string|null, state: string, expense_total: string|null, declined_expenses: int}>}
     */
    public static function of(Employee $employee, Currency $currency): array
    {
        $travelRequests = $employee->travelRequests()
            ->with('assignment.project.client:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->withSum(['travelExpenses as counted_amount' => fn (Builder $expenses) => $expenses->where('state', '!=', TravelExpenseState::Declined)], 'amount')
            ->withCount(['travelExpenses as declined_expenses' => fn (Builder $expenses) => $expenses->where('state', TravelExpenseState::Declined)])
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
            'client' => $travelRequest->assignment->project->client?->name,
            'state' => $travelRequest->state->value,
            'expense_total' => $travelRequest->takesExpenses() ? $currency->format((int) $travelRequest->getAttribute('counted_amount')) : null,
            'declined_expenses' => (int) $travelRequest->getAttribute('declined_expenses'),
        ];

        return [
            'upcoming' => $travelRequests->filter($isUpcoming)->map($row)->values()->all(),
            'past' => $travelRequests->reject($isUpcoming)->reverse()->map($row)->values()->all(),
        ];
    }

    /**
     * One Travel Request in full: the Assignment it is for, the days it
     * counts, who decided it and why it was declined, and whether the actor
     * may cancel it; and, when it takes Travel Expenses, those in the order
     * they were paid, with the total of those not Declined, and whether the
     * actor may record more and change or delete each.
     *
     * @return array{id: int, project: string, client: string|null, destination: string, first_date: string, last_date: string, days: int, purpose: string, state: string, decided_by: string|null, decline_reason: string|null, can_cancel: bool, expenses: array{items: array<int, array{id: int, paid_on: string, category: string, description: string, amount: string, state: string, decline_reason: string|null, can_change: bool}>, total: string, can_create: bool}|null}
     */
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
            'expenses' => $travelRequest->takesExpenses() ? self::expenses($travelRequest, $currency, $actor) : null,
        ];
    }

    /**
     * What names a Travel Request at the top of a Travel Expense's form.
     *
     * @return array{id: int, destination: string, first_date: string, last_date: string}
     */
    public static function summary(TravelRequest $travelRequest): array
    {
        return [
            'id' => $travelRequest->id,
            'destination' => $travelRequest->destination,
            'first_date' => $travelRequest->first_date->toDateString(),
            'last_date' => $travelRequest->last_date->toDateString(),
        ];
    }

    /**
     * The Travel Expenses of a Travel Request that takes them, in the order
     * they were paid, with the total of those not Declined in the company
     * currency, and whether the actor may change each and record more.
     *
     * @return array{items: array<int, array{id: int, paid_on: string, category: string, description: string, amount: string, state: string, decline_reason: string|null, can_change: bool}>, total: string, can_create: bool}
     */
    private static function expenses(TravelRequest $travelRequest, Currency $currency, Employee $actor): array
    {
        $travelExpenses = $travelRequest->travelExpenses()->orderBy('paid_on')->orderBy('id')->get();

        return [
            'items' => $travelExpenses
                ->map(function (TravelExpense $travelExpense) use ($travelRequest, $currency, $actor): array {
                    $travelExpense->setRelation('travelRequest', $travelRequest);

                    return [
                        'id' => $travelExpense->id,
                        'paid_on' => $travelExpense->paid_on->toDateString(),
                        'category' => $travelExpense->category->value,
                        'description' => $travelExpense->description,
                        'amount' => $currency->format($travelExpense->amount),
                        'state' => $travelExpense->state->value,
                        'decline_reason' => $travelExpense->decline_reason,
                        'can_change' => $actor->can('update', $travelExpense),
                    ];
                })
                ->all(),
            'total' => $currency->format((int) $travelExpenses
                ->reject(fn (TravelExpense $travelExpense): bool => $travelExpense->state === TravelExpenseState::Declined)
                ->sum('amount')),
            'can_create' => $actor->can('create', [TravelExpense::class, $travelRequest]),
        ];
    }
}
