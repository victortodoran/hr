<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalsTab;
use App\Enums\Permission;
use App\Enums\TimeOffState;
use App\Enums\WorkEntryState;
use App\Http\Requests\ApprovalFiltersRequest;
use App\Http\Requests\SelectedWorkEntriesRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeOff;
use App\Models\WorkEntry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Approvals page, in two tabs. Work Entries: Work Entries across the Employees the viewer may
 * approve or reopen for (everyone, for someone who can lock), filtered, and
 * approving or reopening many of them at once. Only an Employee's effective
 * Manager approves their Work Entries; only a Role that grants reopening
 * reopens them, being the Manager is not enough. Nobody approves or reopens
 * their own Work Entries; locking is done by the LockController. Time Off: the Pending Time Off the viewer
 * decides as an effective Manager, answered by the TimeOffApprovalController
 * and TimeOffDeclineController.
 */
class ApprovalController extends Controller
{
    /**
     * The open tab, Work Entries unless Time Off is asked for by someone who
     * decides it, with how many Work Entries and Time Off wait for the viewer's
     * decision. Only the open tab's list is loaded.
     */
    public function index(#[CurrentUser] Employee $actor, ApprovalFiltersRequest $request): Response
    {
        $decidesTimeOff = $actor->managesAnyone();
        $tab = $request->tab($decidesTimeOff);

        return Inertia::render('approvals/Index', [
            'tab' => $tab->value,
            'decidesTimeOff' => $decidesTimeOff,
            'waiting' => $actor->waitingForDecision(),
            ...match ($tab) {
                ApprovalsTab::WorkEntries => $this->workEntriesTab($actor, $request, $decidesTimeOff),
                ApprovalsTab::TimeOff => ['toApprove' => $this->timeOffToApprove($actor)],
            },
        ]);
    }

    /**
     * The Work Entries tab: the Work Entries matching the filters, oldest
     * first, with their total hours. Only Employees the viewer may approve or
     * reopen for are listed, never the viewer themselves, unless they can
     * lock, which covers everyone. Opens on Pending Work Entries, or on
     * Approved ones for someone who manages nobody and so can't approve,
     * which is whoever doesn't decide Time Off either.
     *
     * @return array<string, mixed>
     */
    private function workEntriesTab(Employee $actor, ApprovalFiltersRequest $request, bool $canApprove): array
    {
        $canLock = $actor->can('lock', WorkEntry::class);
        $filters = $request->filters($canApprove ? WorkEntryState::Pending : WorkEntryState::Approved);
        $listed = $canLock ? Employee::query() : $this->approvableOrReopenable($actor);
        $matching = $this->entriesOf(clone $listed)->filteredBy($filters);
        $approvable = $this->approvable($actor)->pluck('id');
        $reopenable = $this->reopenable($actor)->pluck('id');

        return [
            'filters' => $filters,
            'canApprove' => $canApprove,
            'canReopen' => $actor->holds(Permission::ReopenWorkEntries),
            'canLock' => $canLock,
            'entries' => (clone $matching)
                ->with(['assignment.employee:id,name', 'assignment.project:id,name', 'approver:id,name', 'locker:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
                ->orderBy('date')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (WorkEntry $entry): array => [
                    'id' => $entry->id,
                    'date' => $entry->date->toDateString(),
                    'employee' => $entry->assignment->employee->only('id', 'name'),
                    'project' => $entry->assignment->project->name,
                    'hours' => $entry->hours,
                    'description' => $entry->description,
                    'state' => $entry->state->value,
                    'approved' => $entry->approval(),
                    'locked' => $entry->lock(),
                    'can_approve' => $entry->isPending() && $approvable->contains($entry->assignment->employee_id),
                    'can_reopen' => $entry->state === WorkEntryState::Approved && $reopenable->contains($entry->assignment->employee_id),
                    'can_lock' => $entry->state === WorkEntryState::Approved && $canLock,
                ]),
            'total' => (int) (clone $matching)->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'matchingIds' => Inertia::optional(fn (): array => (clone $matching)->orderBy('id')->pluck('id')->all()),
            'employees' => $listed
                ->orderBy('name')
                ->orderBy('id') // @pest-mutate-ignore (only orders Employees who share a name)
                ->get(['id', 'name'])
                ->map(fn (Employee $employee): array => $employee->only('id', 'name'))
                ->all(),
            'projects' => Project::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Project $project): array => $project->only('id', 'name'))
                ->all(),
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
            ->with('employee:id,name') // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
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
     * Approve the selected Work Entries the actor may approve: Pending ones
     * of Employees whose effective Manager they are. The rest are skipped,
     * and the toast says how many of each.
     */
    public function store(#[CurrentUser] Employee $actor, SelectedWorkEntriesRequest $request): RedirectResponse
    {
        $ids = $request->ids();

        $approved = $this->entriesOf($this->approvable($actor))
            ->whereKey($ids)
            ->where('state', WorkEntryState::Pending)
            ->update(['state' => WorkEntryState::Approved, 'approved_by' => $actor->id, 'approved_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Work Entries approved|{1} 1 Work Entry approved|[2,*] :count Work Entries approved', $approved),
            count($ids) - $approved,
        )]);

        return back();
    }

    /**
     * Reopen the selected Work Entries the actor may reopen: Approved ones of
     * Employees their reopen Scope covers, never their own, sent back to Pending
     * with their approval withdrawn. The rest are skipped, and the toast says
     * how many of each.
     */
    public function destroy(#[CurrentUser] Employee $actor, SelectedWorkEntriesRequest $request): RedirectResponse
    {
        $ids = $request->ids();

        $reopened = $this->entriesOf($this->reopenable($actor))
            ->whereKey($ids)
            ->where('state', WorkEntryState::Approved)
            ->update(['state' => WorkEntryState::Pending, 'approved_by' => null, 'approved_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->outcome(
            trans_choice('{0} No Work Entries reopened|{1} 1 Work Entry reopened|[2,*] :count Work Entries reopened', $reopened),
            count($ids) - $reopened,
        )]);

        return back();
    }

    /**
     * The Employees whose Work Entries the actor may approve: those whose
     * effective Manager they are.
     *
     * @return Builder<Employee>
     */
    private function approvable(Employee $actor): Builder
    {
        return Employee::query()->effectivelyManagedBy($actor);
    }

    /**
     * The Employees whose Work Entries the actor may reopen: those their
     * reopen Scope covers, never the actor themselves. Being an Employee's
     * Manager doesn't count, so without the Permission this is nobody.
     *
     * @return Builder<Employee>
     */
    private function reopenable(Employee $actor): Builder
    {
        $scope = $actor->widestScopeFor(Permission::ReopenWorkEntries);
        $employees = Employee::query()->whereKeyNot($actor->id);

        if ($scope === null) {
            return $employees->whereRaw('false');
        }

        $scope->limit($employees, $actor);

        return $employees;
    }

    /**
     * The Employees the actor may approve or reopen for, never the actor
     * themselves: whose Work Entries the Work Entries tab lists for someone
     * who can't lock.
     *
     * @return Builder<Employee>
     */
    private function approvableOrReopenable(Employee $actor): Builder
    {
        $scope = $actor->widestScopeFor(Permission::ReopenWorkEntries);

        return Employee::query()
            ->where(function (Builder $covered) use ($actor, $scope): void {
                $covered->effectivelyManagedBy($actor);

                if ($scope !== null) {
                    $covered->orWhere(fn (Builder $employee) => $scope->limit($employee, $actor));
                }
            })
            ->whereKeyNot($actor->id);
    }

    /**
     * The Work Entries of the given Employees.
     *
     * @param  Builder<Employee>  $employees
     * @return Builder<WorkEntry>
     */
    private function entriesOf(Builder $employees): Builder
    {
        return WorkEntry::query()->whereIn('assignment_id', Assignment::query()
            ->select('id')
            ->whereIn('employee_id', $employees->select('employees.id')));
    }
}
