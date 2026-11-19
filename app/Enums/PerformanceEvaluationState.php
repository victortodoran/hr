<?php

namespace App\Enums;

/**
 * Where a Performance Evaluation stands: Open while its Manager can still
 * give, change or remove its Goals and their Outcomes and write its
 * summary, and Completed once the Manager, with all of those in place,
 * has given the whole its Outcome, which closes it.
 */
enum PerformanceEvaluationState: string
{
    case Open = 'open';
    case Completed = 'completed';

    /**
     * Whether anything on it can still change: its Goals, their Outcomes
     * and its summary. Its own Outcome is only given by Completing it.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Open => true,
            self::Completed => false,
        };
    }
}
