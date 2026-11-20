<?php

namespace App\Pages\Performance;

use App\Enums\PerformanceEvaluationState;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;

/**
 * The Performance Evaluations page with the New Performance Evaluation
 * dialog open over it (rule L14), as the props it renders.
 */
final class PerformanceEvaluationFormPage
{
    /**
     * The Performance Evaluations page's props (PerformanceEvaluationListPage)
     * for the list it had, that the dialog is open, and the years a
     * Performance Evaluation may be created for, the current one first,
     * each with the Employees the viewer effectively manages who still
     * lack one for it, by name.
     *
     * @param  array{year: int, search: string, state: PerformanceEvaluationState|null, employee: int|null}  $filters
     * @return array<string, mixed>
     */
    public static function create(Employee $actor, array $filters): array
    {
        return [
            ...PerformanceEvaluationListPage::props($actor, $filters),
            'creating' => true,
            'creatableYears' => array_map(fn (int $creatable): array => [
                'year' => $creatable,
                'employees' => Employee::query()
                    ->awaitingPerformanceEvaluationFrom($actor, $creatable)
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get(['id', 'name'])
                    ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->name])
                    ->all(),
            ], PerformanceEvaluation::creatableYears()),
        ];
    }
}
