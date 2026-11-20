<?php

namespace App\Pages\Performance;

use App\Enums\PerformanceEvaluationState;
use App\Models\Builders\EmployeeBuilder;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Support\PageWithin;
use Carbon\CarbonImmutable;

final class PerformanceEvaluationListPage
{
    /**
     * An Employee filter the viewer may not open here is ignored rather than refused.
     *
     * @param  array{year: int, search: string, state: PerformanceEvaluationState|null, employee: int|null}  $filters
     * @return array<string, mixed>
     */
    public static function props(Employee $actor, array $filters): array
    {
        ['year' => $year, 'search' => $search, 'state' => $state, 'employee' => $employeeId] = $filters;

        $askedEmployee = $employeeId === null ? null : Employee::query()->withPerformanceEvaluationsVisibleTo($actor)->whereKey($employeeId)->first(['employees.id', 'name']);

        $latestYear = max(PerformanceEvaluation::creatableYears());
        $firstStart = Employee::query()->withPerformanceEvaluationsVisibleTo($actor)->min('start_date');
        $years = range($latestYear, min(today()->year, $firstStart === null ? today()->year : CarbonImmutable::parse($firstStart)->year));

        $employees = PageWithin::paginate(Employee::query()
            ->withPerformanceEvaluationsVisibleTo($actor)
            ->withPerformanceEvaluationFor($year, $state)
            ->whereNameContains($search)
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
