<?php

namespace App\Pages\Performance;

use App\Http\Requests\Performance\PerformanceEvaluationListRequest;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Support\PageWithin;
use Carbon\CarbonImmutable;

/**
 * The Performance Evaluations page as the props it renders: one year's
 * list of the Employees whose Performance Evaluations the viewer may open
 * here who have one for it, each with that Performance Evaluation.
 */
final class PerformanceEvaluationListPage
{
    /**
     * The Employees whose Performance Evaluations the viewer may open here
     * (those they effectively manage, and those one of the Permissions for
     * reading them reaches) who have one for the year, Former Employees
     * included, by name, optionally narrowed to part of a name, one state,
     * or one Employee, given back when the viewer may open theirs here and
     * ignored otherwise. Each comes with that Performance Evaluation; those
     * still without one are started from the New Performance Evaluation
     * page. The years to choose from run back from the next one to the
     * earliest Start Date among those Employees.
     *
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, PerformanceEvaluationListRequest $filters): array
    {
        $year = $filters->year();
        $search = $filters->search();
        $state = $filters->state();
        $employeeId = $filters->employeeId();

        $askedEmployee = $employeeId === null ? null : Employee::query()->withPerformanceEvaluationsVisibleTo($actor)->whereKey($employeeId)->first(['employees.id', 'name']);

        $latestYear = max(PerformanceEvaluation::creatableYears());
        $firstStart = Employee::query()->withPerformanceEvaluationsVisibleTo($actor)->min('start_date');
        $years = range($latestYear, min(today()->year, $firstStart === null ? today()->year : CarbonImmutable::parse($firstStart)->year));

        $employees = PageWithin::paginate(Employee::query()
            ->withPerformanceEvaluationsVisibleTo($actor)
            ->withPerformanceEvaluationFor($year, $state)
            ->whereLike('name', '%'.addcslashes($search, '\\%_').'%')
            ->when($askedEmployee, fn (EmployeeBuilder $one, Employee $employee) => $one->whereKey($employee->id))
            ->with(['performanceEvaluations' => fn ($evaluations) => $evaluations->where('year', $year)->withCount('goals')->withAtRiskGoalsCount()]) // @pest-mutate-ignore (eager loading only saves queries; the row reads the same year either way)
            ->orderBy('name')
            ->orderBy('id'), 25)
            ->withQueryString();

        return [
            'year' => $year,
            'years' => $years,
            'search' => $search,
            'state' => $state?->value,
            'employee' => $askedEmployee?->only('id', 'name'),
            'canCreate' => $actor->can('createAny', PerformanceEvaluation::class),
            'employees' => $employees->through(function (Employee $employee): array {
                $evaluation = $employee->performanceEvaluations->sole();

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'evaluation' => $evaluation->listed(),
                ];
            }),
        ];
    }
}
