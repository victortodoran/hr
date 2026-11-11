<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Which entry in an Assignment's Pay history is in effect on a day: the
 * latest one taking effect on or before it. Entries can take effect in the
 * future, so a planned raise is ignored until its day comes.
 */
final class PayHistory
{
    /**
     * The entry in effect on the day, or null when none has taken effect yet.
     *
     * @template TEntry
     *
     * @param  array<string, TEntry>  $entriesByEffectiveDate  Each entry keyed by the date it takes effect, as YYYY-MM-DD
     * @return TEntry|null
     */
    public static function inEffectOn(array $entriesByEffectiveDate, CarbonInterface $day): mixed
    {
        $dayAsKey = $day->toDateString();
        $inEffectFrom = null;

        foreach (array_keys($entriesByEffectiveDate) as $effectiveDate) {
            if ($effectiveDate <= $dayAsKey && ($inEffectFrom === null || $effectiveDate > $inEffectFrom)) { // @pest-mutate-ignore (array keys are never equal, so > and >= agree)
                $inEffectFrom = $effectiveDate;
            }
        }

        return $inEffectFrom === null ? null : $entriesByEffectiveDate[$inEffectFrom];
    }
}
