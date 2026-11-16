<?php

namespace App\Support;

use App\Enums\TravelExpenseState;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An Employee's Travel Requests with their Travel Expenses, as the Travel
 * page and the Travel tab of the Employee page both show them. On the
 * Travel page they come with what the Employee may do to each; the
 * Employee page only shows them, so there, nothing may be done.
 */
final class TravelHistory
{
    /**
     * The Employee's Travel Requests, latest first, with the Assignment each
     * is for, the days it counts, who decided it and why it was declined,
     * and whether the actor, when there is one, may cancel it; and the
     * Travel Expenses of those that take them, with their total.
     *
     * @return array<int, array{id: int, project: string, client: string|null, destination: string, first_date: string, last_date: string, days: int, purpose: string, state: string, decided_by: string|null, decline_reason: string|null, can_cancel: bool, expenses: array{items: array<int, array<string, mixed>>, total: string, can_create: bool}|null}>
     */
    public static function of(Employee $employee, Currency $currency, ?Employee $actor = null): array
    {
        return $employee->travelRequests()
            ->with(['assignment.project.client:id,name', 'decider:id,name', 'travelExpenses' => fn (HasMany $expenses) => $expenses->orderBy('paid_on')->orderBy('id')]) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderByDesc('first_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TravelRequest $travelRequest): array => [
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
                'can_cancel' => $actor?->can('cancel', $travelRequest) ?? false,
                'expenses' => $travelRequest->takesExpenses() ? self::expenses($travelRequest, $currency, $actor) : null,
            ])
            ->all();
    }

    /**
     * The Travel Expenses of a Travel Request that takes them, with the
     * total of those not Declined in the company currency, and whether the
     * actor, when there is one, may change each and record more.
     *
     * @return array{items: array<int, array<string, mixed>>, total: string, can_create: bool}
     */
    private static function expenses(TravelRequest $travelRequest, Currency $currency, ?Employee $actor): array
    {
        return [
            'items' => $travelRequest->travelExpenses
                ->map(function (TravelExpense $travelExpense) use ($travelRequest, $currency, $actor): array {
                    $travelExpense->setRelation('travelRequest', $travelRequest);

                    return [
                        'id' => $travelExpense->id,
                        'paid_on' => $travelExpense->paid_on->toDateString(),
                        'category' => $travelExpense->category->value,
                        'description' => $travelExpense->description,
                        'amount' => $currency->format($travelExpense->amount),
                        'amount_typed' => $currency->toAmount($travelExpense->amount),
                        'state' => $travelExpense->state->value,
                        'decline_reason' => $travelExpense->decline_reason,
                        'can_change' => $actor?->can('update', $travelExpense) ?? false,
                    ];
                })
                ->all(),
            'total' => $currency->format((int) $travelRequest->travelExpenses
                ->reject(fn (TravelExpense $travelExpense): bool => $travelExpense->state === TravelExpenseState::Declined)
                ->sum('amount')),
            'can_create' => $actor?->can('create', [TravelExpense::class, $travelRequest]) ?? false,
        ];
    }
}
