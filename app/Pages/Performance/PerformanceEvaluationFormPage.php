<?php

namespace App\Pages\Performance;

use App\Enums\PerformanceEvaluationState;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;

final class PerformanceEvaluationFormPage
{
    /**
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
