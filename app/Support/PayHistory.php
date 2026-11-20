<?php

namespace App\Support;

use App\Enums\PayChangeState;
use App\Models\Assignment;
use App\Models\PayEntry;
use Carbon\CarbonInterface;

/** Entries can take effect in the future, so a planned raise is ignored until its day comes. */
final class PayHistory
{
    /**
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

    /** @return list<array{entry: PayEntry, until: CarbonInterface|null, state: PayChangeState}> */
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
