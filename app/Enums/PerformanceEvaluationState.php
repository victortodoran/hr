<?php

namespace App\Enums;

/**
 * Where a Performance Evaluation stands: Open while its Manager can still
 * give, change or remove its Goals.
 */
enum PerformanceEvaluationState: string
{
    case Open = 'open';

    /**
     * Whether its Goals can still be given, changed or removed.
     */
    public function takesGoals(): bool
    {
        return match ($this) {
            self::Open => true,
        };
    }
}
