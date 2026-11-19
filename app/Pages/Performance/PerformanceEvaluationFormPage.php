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
     * who still lack one for it, by name; and the year and Employee to
     * start with: the ones asked for when that Employee is among the
     * year's, otherwise the current year and nobody.
     *
     * @return array{years: list<array{year: int, employees: array<int, array{id: int, name: string}>}>, chosenYear: int, chosenEmployeeId: int|null}
     */
    public static function create(Employee $actor, ?int $askedYear, ?int $askedEmployeeId): array
    {
        $years = array_map(fn (int $year): array => [
            'year' => $year,
            'employees' => Employee::query()
                ->awaitingPerformanceEvaluationFrom($actor, $year)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->name])
                ->all(),
        ], PerformanceEvaluation::creatableYears());

        foreach ($years as $option) {
            if ($option['year'] === $askedYear && in_array($askedEmployeeId, array_column($option['employees'], 'id'), true)) {
                return ['years' => $years, 'chosenYear' => $option['year'], 'chosenEmployeeId' => $askedEmployeeId];
            }
        }

        return ['years' => $years, 'chosenYear' => $years[0]['year'], 'chosenEmployeeId' => null];
    }
}
