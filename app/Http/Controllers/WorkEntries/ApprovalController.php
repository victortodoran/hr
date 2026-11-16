<?php

namespace App\Http\Controllers\WorkEntries;

use App\Enums\ApprovalsTab;
use App\Enums\TimeOffState;
use App\Enums\TravelRequestState;
use App\Enums\WorkEntryState;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkEntries\ApprovalFiltersRequest;
use App\Http\Requests\WorkEntries\SelectedWorkEntriesRequest;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeOff;
use App\Models\TravelRequest;
use App\Models\WorkEntry;
use App\Support\PageWithin;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Approvals page, an inbox of what waits for the viewer's decision as an
 * effective Manager, in two tabs. Work Entries: the Pending Work Entries of
 * the Employees whose effective Manager the viewer is, one Employee and one
 * Project at a time, and approving many of them at once, or declining them
 * with the WorkEntryDeclineController. Nobody approves or declines their own
 * Work Entries.
 * Reopening and locking happen on the Work Entries page. Time Off: the
 * Pending Time Off the viewer decides, answered by the
 * TimeOffApprovalController and TimeOffDeclineController. Travel Requests:
 * the Pending Travel Requests the viewer decides, answered by the
 * TravelRequestApprovalController and TravelRequestDeclineController.
 */
class ApprovalController extends Controller
{
    /**
     * The open tab, Work Entries unless Time Off or Travel Requests are
     * asked for, with how many Work Entries, Time Off and Travel Requests
     * wait for the viewer's decision. Only the open tab's list is loaded.
     */
    public function index(#[CurrentUser] Employee $actor, ApprovalFiltersRequest $request): Response
    {
        $tab = $request->tab();

        return Inertia::render('approvals/Index', [
            'tab' => $tab->value,
            'waiting' => $actor->waitingForDecision(),
            ...match ($tab) {
                ApprovalsTab::WorkEntries => $this->workEntriesTab($actor, $request),
                ApprovalsTab::TimeOff => ['toApprove' => $this->timeOffToApprove($actor)],
                ApprovalsTab::TravelRequests => ['travelRequestsToApprove' => $this->travelRequestsToApprove($actor)],
            },
        ]);
    }

    /**
     * The Work Entries tab, one Employee and one Project at a time: the
     * Pending Work Entries of the chosen Employee on the chosen Project,
     * oldest first, with their total hours. The Employees to choose from are
     * those whose effective Manager the viewer is and who have Pending Work
     * Entries, and the Projects those the chosen Employee has Pending Work
     * Entries on, each by name with how many. An Employee or Project not
     * among them, or none, falls back to the first, so approving the last of
     * one moves on to the first left. With nothing Pending at all, there is
     * nobody to choose.
     *
     * @return array<string, mixed>
     */
    private function workEntriesTab(Employee $actor, ApprovalFiltersRequest $request): array
    {
        $requested = $request->filters();
        $isPending = fn (Builder $entries): Builder => $entries->where('work_entries.state', WorkEntryState::Pending);
        $employees = Employee::query()
            ->effectivelyManagedBy($actor)
            ->whereHas('workEntries', $isPending)
            ->withCount(['workEntries as pending' => $isPending])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Employees who share a name)
            ->get(['id', 'name'])
            ->map(fn (Employee $employee): array => [...$employee->only('id', 'name'), 'pending' => (int) $employee->getAttribute('pending')])
            ->all();
        $employee = collect($employees)->firstWhere('id', $requested['employee']) ?? $employees[0] ?? null;
        $isPendingOfEmployee = fn (Builder $entries): Builder => $isPending($entries)->where('assignments.employee_id', $employee['id'] ?? null);
        $projects = $employee === null ? [] : Project::query()
            ->whereHas('workEntries', $isPendingOfEmployee)
            ->withCount(['workEntries as pending' => $isPendingOfEmployee])
            ->orderBy('name')
            ->orderBy('id') // @pest-mutate-ignore (only orders Projects that share a name)
            ->get(['id', 'name'])
            ->map(fn (Project $project): array => [...$project->only('id', 'name'), 'pending' => (int) $project->getAttribute('pending')])
            ->all();
        $project = collect($projects)->firstWhere('id', $requested['project']) ?? $projects[0] ?? null;
        $filters = [
            'employee' => $employee['id'] ?? null,
            'project' => $project['id'] ?? null,
        ];
        $matching = WorkEntry::query()
            ->where('state', WorkEntryState::Pending)
            ->whereRelation('assignment', 'employee_id', $filters['employee'])
            ->whereRelation('assignment', 'project_id', $filters['project']);

        return [
            'filters' => $filters,
            'entries' => PageWithin::paginate((clone $matching)
                ->with(['assignment.employee:id,name', 'assignment.project:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderBy('date')
                ->orderBy('id'), 25)
                ->withQueryString()
                ->through(fn (WorkEntry $entry): array => [
                    'id' => $entry->id,
                    'date' => $entry->date->toDateString(),
                    'employee' => $entry->assignment->employee->only('id', 'name'),
                    'project' => $entry->assignment->project->name,
                    'hours' => $entry->hours,
                    'description' => $entry->description,
                ]),
            'total' => (int) (clone $matching)->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'matchingIds' => Inertia::optional(fn (): array => (clone $matching)->orderBy('id')->pluck('id')->all()),
            'employees' => $employees,
            'projects' => $projects,
        ];
    }

    /**
     * The Time Off tab: the Pending Time Off of the Employees whose effective
     * Manager the actor is, earliest first, each with that Employee's Paid
     * Days left in every year it touches. Paid Days left already count the
     * Pending Time Off.
     *
     * @return array<int, array<string, mixed>>
     */
    private function timeOffToApprove(Employee $actor): array
    {
        return TimeOff::query()
            ->whereIn('employee_id', Employee::query()->effectivelyManagedBy($actor)->select('employees.id'))
            ->where('state', TimeOffState::Pending)
            ->with('employee:id,name,paid_days') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('first_date')
            ->orderBy('id')
            ->get()
            ->map(fn (TimeOff $timeOff): array => [
                'id' => $timeOff->id,
                'employee' => $timeOff->employee->only('id', 'name'),
                'first_date' => $timeOff->first_date->toDateString(),
                'last_date' => $timeOff->last_date->toDateString(),
                'days' => $timeOff->weekdays(),
                'is_paid' => $timeOff->is_paid,
                'paid_days_left' => array_map(fn (int $year): array => [
                    'year' => $year,
                    'days' => $timeOff->employee->paidDaysLeftIn($year),
                ], range($timeOff->first_date->year, $timeOff->last_date->year)),
            ])
            ->all();
    }

    /**
     * The Travel Requests tab: the Pending Travel Requests of the Employees
     * whose effective Manager the actor is, earliest first, each with its
     * Assignment's Project and Client and the days it counts.
     *
     * @return array<int, array<string, mixed>>
     */
    private function travelRequestsToApprove(Employee $actor): array
    {
        return TravelRequest::query()
            ->whereIn('employee_id', Employee::query()->effectivelyManagedBy($actor)->select('employees.id'))
            ->where('state', TravelRequestState::Pending)
            ->with(['employee:id,name', 'assignment.project.client:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('first_date')
            ->orderBy('id')
            ->get()
            ->map(fn (TravelRequest $travelRequest): array => [
                'id' => $travelRequest->id,
                'employee' => $travelRequest->employee->only('id', 'name'),
                'project' => $travelRequest->assignment->project->name,
                'client' => $travelRequest->assignment->project->client?->name,
                'destination' => $travelRequest->destination,
                'first_date' => $travelRequest->first_date->toDateString(),
                'last_date' => $travelRequest->last_date->toDateString(),
                'days' => $travelRequest->days(),
                'purpose' => $travelRequest->purpose,
            ])
            ->all();
    }

    /**
     * Approve the selected Work Entries the actor may approve: Pending ones
     * of Employees whose effective Manager they are. The rest are skipped,
     * and the toast says how many of each.
     */
    public function store(#[CurrentUser] Employee $actor, SelectedWorkEntriesRequest $request): RedirectResponse
    {
        $ids = $request->ids();

        $approved = WorkEntry::query()
            ->ofEmployees(Employee::query()->effectivelyManagedBy($actor))
            ->whereKey($ids)
            ->where('state', WorkEntryState::Pending)
            ->update(['state' => WorkEntryState::Approved, 'approved_by' => $actor->id, 'approved_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Work Entries approved|{1} 1 Work Entry approved|[2,*] :count Work Entries approved', $approved),
            count($ids) - $approved,
        )]);

        return back();
    }
}
