<?php

namespace App\Support;

use App\Models\PerformanceEvaluation;

final class PerformanceEvaluationHeading
{
    /**
     * @return array{id: int, year: int, employee: array{id: int, name: string}}
     */
    public static function of(PerformanceEvaluation $performanceEvaluation): array
    {
        return [
            'id' => $performanceEvaluation->id,
            'year' => $performanceEvaluation->year,
            'employee' => $performanceEvaluation->employee->only('id', 'name'),
        ];
    }
}
