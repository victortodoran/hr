<?php

namespace App\Http\Controllers\Travel;

use App\Enums\TravelExpenseState;
use App\Enums\TravelRequestState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelRequestRequest;
use App\Models\Assignment;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Support\Currency;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Travel page: the signed-in Employee's own Travel Requests, and making
 * one.
 */
class TravelRequestController extends Controller
{
    /**
     * Show the Employee's Travel Requests, latest first, with the Assignment
     * each is for, the days it counts, who decided it and why it was
     * declined, and whether they may still cancel it; the Travel Expenses
     * of those that take them, with their total; and the Assignments they
     * can still travel on, those not ended before today.
     */
    public function index(#[CurrentUser] Employee $actor): Response
    {
        $currency = CompanySettings::payCurrency();

        return Inertia::render('travel-requests/Index', [
            'today' => today()->toDateString(),
            'currency' => ['code' => $currency->code, 'decimals' => $currency->decimals()],
            'travelRequests' => $actor->travelRequests()
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
                    'can_cancel' => $actor->can('cancel', $travelRequest),
                    'expenses' => $travelRequest->takesExpenses() ? $this->expenses($travelRequest, $currency, $actor) : null,
                ])
                ->all(),
            'assignments' => $actor->assignments()
                ->where(fn (Builder $open) => $open->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                ->with('project.client:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->get()
                ->sortBy('project.name')
                ->map(fn (Assignment $assignment): array => [
                    'id' => $assignment->id,
                    'project' => $assignment->project->name,
                    'client' => $assignment->project->client?->name,
                    'start_date' => $assignment->start_date->toDateString(),
                    'end_date' => $assignment->end_date?->toDateString(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Make a Travel Request for the signed-in Employee. It is Pending until
     * their Manager decides, except for an Employee with no Manager, whose
     * Travel Request is Approved at once with nobody deciding.
     */
    public function store(TravelRequestRequest $request, #[CurrentUser] Employee $actor): RedirectResponse
    {
        $travelRequest = $actor->travelRequests()->make($request->travelRequest());

        if ($actor->hasNoManager()) {
            $travelRequest->forceFill(['state' => TravelRequestState::Approved, 'decided_at' => now()]);
        }

        $travelRequest->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Travel Request made.')]);

        return to_route('travel-requests.index');
    }

    /**
     * The Travel Expenses of a Travel Request that takes them, with the
     * total of those not Declined in the company currency, and whether the
     * Employee may record more.
     *
     * @return array{items: array<int, array<string, mixed>>, total: string, can_create: bool}
     */
    private function expenses(TravelRequest $travelRequest, Currency $currency, Employee $actor): array
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
                        'can_change' => $actor->can('update', $travelExpense),
                    ];
                })
                ->all(),
            'total' => $currency->format((int) $travelRequest->travelExpenses
                ->reject(fn (TravelExpense $travelExpense): bool => $travelExpense->state === TravelExpenseState::Declined)
                ->sum('amount')),
            'can_create' => $actor->can('create', [TravelExpense::class, $travelRequest]),
        ];
    }
}
