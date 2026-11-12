<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Http\Requests\WorkEntryRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The week of Work Entries for one Employee, and recording, correcting and
 * deleting them.
 */
class WorkEntryController extends Controller
{
    /**
     * One Employee's Work Entries for one week, Monday to Sunday, with totals
     * for each day, each Assignment and the week. Opens on the viewer's own
     * current week. An Employee who doesn't exist is refused just like one
     * outside the viewer's Scope, so the page never reveals who exists.
     */
    public function index(#[CurrentUser] Employee $actor, Request $request): Response
    {
        $request->validate([
            'employee' => ['nullable', 'integer'],
            'week' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $employee = $request->filled('employee') ? Employee::query()->find($request->integer('employee')) : $actor;
        abort_if($employee === null, 403);
        Gate::authorize('viewAny', [WorkEntry::class, $employee]);
        $monday = CarbonImmutable::parse($request->string('week')->value() ?: today())->startOfWeek(CarbonInterface::MONDAY);
        $sunday = $monday->endOfWeek(CarbonInterface::SUNDAY);

        $entries = $employee->workEntries()
            ->whereBetween('work_entries.date', [$monday->toDateString(), $sunday->toDateString()])
            ->with(['assignment.project:id,name', 'approver:id,name', 'locker:id,name']) // @pest-mutate-ignore (eager loading only saves queries; lazy loading gives the same props)
            ->orderBy('work_entries.date')
            ->orderBy('work_entries.id')
            ->get();

        $canManage = $actor->can('create', [WorkEntry::class, $employee]);

        return Inertia::render('work-entries/Index', [
            'employee' => $employee->only('id', 'name'),
            'week' => $monday->toDateString(),
            'today' => today()->toDateString(),
            'days' => $this->days($monday, $entries, $canManage),
            'assignmentTotals' => $entries->groupBy('assignment_id')
                ->map(fn (Collection $onAssignment): array => [
                    'assignment_id' => $onAssignment->firstOrFail()->assignment_id,
                    'project' => $onAssignment->firstOrFail()->assignment->project->name,
                    'hours' => $onAssignment->sum('hours'),
                ])
                ->sortBy('project')
                ->values(),
            'total' => $entries->sum('hours'),
            'assignments' => $employee->assignments()
                ->whereDate('start_date', '<=', $sunday)
                ->where(fn (Builder $running) => $running->whereNull('end_date')->orWhereDate('end_date', '>=', $monday))
                ->with('project:id,name')
                ->get()
                ->sortBy('project.name')
                ->map(fn (Assignment $assignment): array => [
                    'id' => $assignment->id,
                    'project' => $assignment->project->name,
                    'job_title' => $assignment->job_title,
                    'start_date' => $assignment->start_date->toDateString(),
                    'end_date' => $assignment->end_date?->toDateString(),
                ])
                ->values(),
            'canManage' => $canManage,
            'employees' => $this->pickableEmployees($actor),
        ]);
    }

    /**
     * Monday to Sunday, each with its Work Entries and their total. Each
     * Work Entry carries its state, who approved and locked it, and whether
     * the viewer can change it, which needs manage work entries for the
     * Employee and a Pending Work Entry.
     *
     * @param  Collection<int, WorkEntry>  $entries
     * @return array<int, array{date: string, entries: array<int, array<string, mixed>>, hours: float|int}>
     */
    private function days(CarbonImmutable $monday, Collection $entries, bool $canManage): array
    {
        return collect(range(0, 6))->map(function (int $offset) use ($monday, $entries, $canManage): array {
            $date = $monday->addDays($offset)->toDateString();
            $onDay = $entries->filter(fn (WorkEntry $entry): bool => $entry->date->toDateString() === $date);

            return [
                'date' => $date,
                'entries' => $onDay->map(fn (WorkEntry $entry): array => [
                    'id' => $entry->id,
                    'assignment_id' => $entry->assignment_id,
                    'project' => $entry->assignment->project->name,
                    'hours' => $entry->hours,
                    'description' => $entry->description,
                    'state' => $entry->state->value,
                    'approved' => $entry->approval(),
                    'locked' => $entry->lock(),
                    'can_change' => $canManage && $entry->isPending(),
                ])->values()->all(),
                'hours' => $onDay->sum('hours'),
            ];
        })->all();
    }

    /**
     * The Employees whose Work Entries the viewer may see, for switching
     * between them, or null when they may only see their own.
     *
     * @return array<int, array{id: int, name: string}>|null
     */
    private function pickableEmployees(Employee $actor): ?array
    {
        $scopes = collect([Permission::ViewWorkEntries, Permission::ManageWorkEntries])
            ->map(fn (Permission $permission): ?Scope => $actor->widestScopeFor($permission))
            ->filter();

        if (! $scopes->contains(fn (Scope $scope): bool => $scope->coversOthers())) {
            return null;
        }

        return Employee::query()
            ->where(function (Builder $covered) use ($scopes, $actor): void {
                $covered->whereRaw('false');
                $scopes->each(fn (Scope $scope) => $covered->orWhere(fn (Builder $employee) => $scope->limit($employee, $actor)));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Employee $employee): array => $employee->only('id', 'name'))
            ->all();
    }

    /**
     * Record hours for the Employee.
     */
    public function store(WorkEntryRequest $request, Employee $employee): RedirectResponse
    {
        $workEntry = WorkEntry::query()->create($request->workEntry());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work Entry added.')]);

        return $this->toWeekOf($employee, $workEntry);
    }

    /**
     * Correct a Work Entry: its Assignment, date, hours or description.
     */
    public function update(WorkEntryRequest $request, WorkEntry $workEntry): RedirectResponse
    {
        $workEntry->update($request->workEntry());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work Entry changed.')]);

        return $this->toWeekOf($request->employee(), $workEntry);
    }

    /**
     * Delete a Work Entry recorded by mistake.
     */
    public function destroy(WorkEntry $workEntry): RedirectResponse
    {
        $workEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work Entry deleted.')]);

        return $this->toWeekOf($workEntry->employee, $workEntry);
    }

    /**
     * Back to the week the Work Entry falls in.
     */
    private function toWeekOf(Employee $employee, WorkEntry $workEntry): RedirectResponse
    {
        return to_route('work-entries.index', ['employee' => $employee->id, 'week' => $workEntry->date->startOfWeek(CarbonInterface::MONDAY)->toDateString()]);
    }
}
