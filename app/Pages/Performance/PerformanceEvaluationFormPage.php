<?php

namespace App\Pages\Performance;

use App\Models\Employee;
use App\Models\PerformanceEvaluation;

/**
 * The New Performance Evaluation page as the props it renders.
 */
final class PerformanceEvaluationFormPage
{
    /**
     * The years a Performance Evaluation may be created for, the current
     * one first, each with the Employees the viewer effectively manages
     * who still lack one for it, by name.
     *
     * @return array{years: list<array{year: int, employees: array<int, array{id: int, name: string}>}>}
     */
    public static function create(Employee $actor): array
    {
        return [
            'years' => array_map(fn (int $year): array => [
                'year' => $year,
                'employees' => Employee::query()
                    ->effectivelyManagedBy($actor)
                    ->awaitingPerformanceEvaluationFor($year)
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get(['id', 'name'])
                    ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->name])
                    ->all(),
            ], PerformanceEvaluation::creatableYears()),
        ];
    }
}
