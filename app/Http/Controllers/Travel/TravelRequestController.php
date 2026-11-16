<?php

namespace App\Http\Controllers\Travel;

use App\Enums\TravelRequestState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelRequestRequest;
use App\Models\Assignment;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Support\TravelHistory;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
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
     * Show the Employee's Travel Requests with their Travel Expenses
     * (TravelHistory), and the Assignments they can still travel on, those
     * not ended before today.
     */
    public function index(#[CurrentUser] Employee $actor): Response
    {
        $currency = CompanySettings::payCurrency();

        return Inertia::render('travel-requests/Index', [
            'today' => today()->toDateString(),
            'currency' => ['code' => $currency->code, 'decimals' => $currency->decimals()],
            'travelRequests' => TravelHistory::of($actor, $currency, $actor),
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
}
