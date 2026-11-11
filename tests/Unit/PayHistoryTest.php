<?php

use App\Support\PayHistory;
use Illuminate\Support\Carbon;

covers(PayHistory::class);

it('finds the Pay in effect on a day: the latest entry taking effect on or before it', function (string $day, ?string $inEffect) {
    $entries = ['2026-06-01' => 'raise', '2026-01-01' => 'first', '2027-01-01' => 'planned raise'];

    expect(PayHistory::inEffectOn($entries, Carbon::parse($day)))->toBe($inEffect === null ? null : $entries[$inEffect]);
})->with([
    'before the first entry' => ['2025-12-31', null],
    'on the first entry' => ['2026-01-01', '2026-01-01'],
    'between entries' => ['2026-05-31', '2026-01-01'],
    'on a raise' => ['2026-06-01', '2026-06-01'],
    'before a planned raise' => ['2026-12-31', '2026-06-01'],
    'after the last entry' => ['2030-01-01', '2027-01-01'],
]);

it('finds no Pay in effect when there is no history', function () {
    expect(PayHistory::inEffectOn([], Carbon::parse('2026-03-01')))->toBeNull();
});
