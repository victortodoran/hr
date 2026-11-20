<?php

namespace App\Enums;

enum PerformanceEvaluationState: string
{
    case Open = 'open';
    case Completed = 'completed';

    /** Its own Outcome is only given by Completing it. */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Open => true,
            self::Completed => false,
        };
    }
}
