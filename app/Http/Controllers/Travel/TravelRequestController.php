<?php

namespace App\Http\Controllers\Travel;

use App\Enums\TravelRequestState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\TravelRequestRequest;
use App\Models\Assignment;
use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Support\TravelHistory;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Travel page: the signed-in Employee's own Travel Requests, making
 * one, and the page of each Travel Request.
 */
class TravelRequestController extends Controller
{
    /**
     * Show the Employee's Travel Requests, Upcoming and Past
     * (TravelHistory).
     */
    public function index(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Index', [
            'travelRequests' => TravelHistory::of($actor, CompanySettings::payCurrency()),
        ]);
    }

    /**
     * Show the form for making a Travel Request, with today and the
     * Assignments the Employee can still travel on, those not ended before
     * today, with their dates.
     */
    public function create(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Create', [
            'today' => today()->toDateString(),
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
     * Show one Travel Request in full with its Travel Expenses, and what the
     * viewer may do to them, which only the Employee themselves ever may.
     * Whether it is the viewer's own sets where its breadcrumbs lead: their
     * Travel page, or the Employee's record when the viewer may open it.
     */
    public function show(TravelRequest $travelRequest, #[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('travel-requests/Show', [
            'travelRequest' => TravelHistory::detail($travelRequest, CompanySettings::payCurrency(), $actor),
            'employee' => $travelRequest->employee->only('id', 'name'),
            'isOwn' => $travelRequest->employee_id === $actor->id,
            'canViewEmployee' => $actor->can('view', $travelRequest->employee),
        ]);
    }
}
