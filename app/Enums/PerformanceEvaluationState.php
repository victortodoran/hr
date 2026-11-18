<?php

namespace App\Enums;

/**
 * Where a Performance Evaluation stands: Open while its Manager can still
 * give, change or remove its Goals and their Outcomes, and Completed once
 * the Manager has given every Goal and the whole an Outcome and closed it.
 */
enum PerformanceEvaluationState: string
{
    case Open = 'open';
    case Completed = 'completed';

    /**
     * Whether its Goals, their Outcomes and its summary can still be
     * given, changed or removed.
     */
    public function takesGoals(): bool
    {
        return match ($this) {
            self::Open => true,
            self::Completed => false,
        };
    }
}
