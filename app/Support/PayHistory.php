<?php

namespace App\Support;

use App\Enums\PayChangeState;
use App\Models\Assignment;
use App\Models\PayEntry;
use Carbon\CarbonInterface;

/**
 * Which entry in an Assignment's Pay history is in effect on a day: the
 * latest one taking effect on or before it. Entries can take effect in the
 * future, so a planned raise is ignored until its day comes. Each entry
 * applies until the day before the next one, the last until the
 * Assignment's end.
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

    /**
     * The Assignment's Pay changes, newest first, each with the last day it
     * applies, the day before the next one or the Assignment's end date
     * (null while it has none), and where it stands on the day.
     *
     * @return list<array{entry: PayEntry, until: CarbonInterface|null, state: PayChangeState}>
     */
    public static function changes(Assignment $assignment, CarbonInterface $day): array
    {
        $entries = array_values($assignment->payEntries->sortByDesc(fn (PayEntry $entry): string => $entry->effective_from->toDateString())->all());
        $inEffect = self::inEffectOn(
            array_combine(array_map(fn (PayEntry $entry): string => $entry->effective_from->toDateString(), $entries), $entries),
            $day,
        );

        return array_map(fn (PayEntry $entry, int $index): array => [
            'entry' => $entry,
            'until' => $index === 0 ? $assignment->end_date : $entries[$index - 1]->effective_from->copy()->subDay(),
            'state' => match (true) {
                $entry->is($inEffect) => PayChangeState::InEffect,
                $entry->effective_from->isAfter($day) => PayChangeState::Upcoming,
                default => PayChangeState::Past,
            },
        ], $entries, array_keys($entries));
    }
}
