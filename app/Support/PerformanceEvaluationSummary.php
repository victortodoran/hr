<?php

namespace App\Support;

use App\Models\PerformanceEvaluation;

/**
 * What a Goal's pages show of its Performance Evaluation, the Goal forms
 * and the Goal's own page alike: whose it is and for which year.
 */
final class PerformanceEvaluationSummary
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
