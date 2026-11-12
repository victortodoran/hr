<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\WorkEntryState;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkEntry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Approvals page: Work Entries across the Employees the viewer may
 * approve, filtered, and approving many of them at once.
 */
class ApprovalController extends Controller
{
    /**
     * The Work Entries matching the filters, oldest first, with their total
     * hours. Only Employees the viewer may approve are listed, never the
     * viewer themselves. Opens on Pending Work Entries.
     */
    public function index(#[CurrentUser] Employee $actor, Request $request): Response
    {
        $filters = $request->validate([
            'employee' => ['nullable', 'integer'],
            'project' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'state' => ['nullable', Rule::enum(WorkEntryState::class)],
        ]);
        $filters = [
            'employee' => isset($filters['employee']) ? (int) $filters['employee'] : null,
            'project' => isset($filters['project']) ? (int) $filters['project'] : null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'state' => $filters['state'] ?? WorkEntryState::Pending->value,
        ];
        $matching = $this->matching($actor, $filters);

        return Inertia::render('approvals/Index', [
            'filters' => $filters,
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
                    'can_approve' => $entry->isPending(),
                ]),
            'total' => (int) (clone $matching)->sum('minutes') / 60, // @pest-mutate-ignore (the database sums whole minutes; the cast only gives the sum a type)
            'matchingIds' => Inertia::optional(fn (): array => (clone $matching)->orderBy('id')->pluck('id')->all()),
            'employees' => $this->approvable($actor)
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
        ]);
    }

    /**
     * The Employees whose Work Entries the actor may approve: those their
     * approve work entries Scope covers, never themselves.
     *
     * @return Builder<Employee>
     */
    private function approvable(Employee $actor): Builder
    {
        return Employee::query()->coveredBy($actor, Permission::ApproveWorkEntries)->whereKeyNot($actor->id);
    }

    /**
     * Approve the selected Work Entries the actor may approve: Pending ones
     * of Employees their Scope covers, never their own. The rest are skipped,
     * and the toast says how many of each.
     */
    public function store(#[CurrentUser] Employee $actor, Request $request): RedirectResponse
    {
        $ids = $this->selected($request);

        $approved = $this->approvableEntries($actor)
            ->whereKey($ids)
            ->where('state', WorkEntryState::Pending)
            ->update(['state' => WorkEntryState::Approved, 'approved_by' => $actor->id, 'approved_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->outcome(
            trans_choice('{0} No Work Entries approved|{1} 1 Work Entry approved|[2,*] :count Work Entries approved', $approved),
            count($ids) - $approved,
        )]);

        return back();
    }

    /**
     * The IDs of the selected Work Entries, each once.
     *
     * @return array<int, int>
     */
    private function selected(Request $request): array
    {
        $message = __('Select at least one Work Entry.');
        $request->validate([
            'ids' => ['required', 'list'],
            'ids.*' => ['integer'],
        ], ['ids.required' => $message, 'ids.list' => $message]);

        return collect($request->array('ids'))->map(intval(...))->unique()->values()->all();
    }

    /**
     * What a bulk action did, such as "10 Work Entries approved, 5 skipped."
     */
    private function outcome(string $done, int $skipped): string
    {
        return $skipped > 0 ? __(':done, :skipped skipped.', ['done' => $done, 'skipped' => $skipped]) : $done.'.';
    }

    /**
     * The Work Entries of the Employees the actor may approve.
     *
     * @return Builder<WorkEntry>
     */
    private function approvableEntries(Employee $actor): Builder
    {
        return WorkEntry::query()->whereIn('assignment_id', Assignment::query()
            ->select('id')
            ->whereIn('employee_id', $this->approvable($actor)->select('employees.id')));
    }

    /**
     * The Work Entries of the Employees the actor may approve that match the
     * filters.
     *
     * @param  array{employee: int|null, project: int|null, from: string|null, to: string|null, state: string}  $filters
     * @return Builder<WorkEntry>
     */
    private function matching(Employee $actor, array $filters): Builder
    {
        return $this->approvableEntries($actor)
            ->when($filters['employee'], fn (Builder $entries, int $employee) => $entries->whereRelation('assignment', 'employee_id', $employee))
            ->when($filters['project'], fn (Builder $entries, int $project) => $entries->whereRelation('assignment', 'project_id', $project))
            ->when($filters['from'], fn (Builder $entries, string $from) => $entries->whereDate('date', '>=', $from))
            ->when($filters['to'], fn (Builder $entries, string $to) => $entries->whereDate('date', '<=', $to))
            ->where('state', $filters['state']);
    }
}
