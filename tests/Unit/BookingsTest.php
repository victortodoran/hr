<?php

use App\Support\Booking;
use App\Support\Bookings;
use Carbon\CarbonImmutable;

covers(Booking::class, Bookings::class);

/**
 * A Booking from plain values: dates as Y-m-d, a null end for open-ended.
 */
function booking(string $start, ?string $end, ?int $share, bool $isInternal = false): Booking
{
    return new Booking(CarbonImmutable::parse($start), $end === null ? null : CarbonImmutable::parse($end), $share, $isInternal);
}

/**
 * An Employee's Bookings: internal from 2026-01-01 with no planned share,
 * Relaunch at 60% through March, Audit at 50% from 15 March.
 */
function typicalBookings(?int $internalShare = null): Bookings
{
    return new Bookings([
        booking('2026-01-01', null, $internalShare, isInternal: true),
        booking('2026-01-01', '2026-03-31', 60),
        booking('2026-03-15', null, 50),
    ]);
}

it('adds up the planned shares on a day, counting an unplanned internal share as nothing', function (string $day, int $total) {
    expect(typicalBookings()->plannedShareOn(CarbonImmutable::parse($day)))->toBe($total);
})->with([
    'before anything starts' => ['2025-12-31', 0],
    'one Assignment' => ['2026-03-14', 60],
    'two overlapping, from the day the second starts' => ['2026-03-15', 110],
    'on the last day of the first' => ['2026-03-31', 110],
    'the day after it ends' => ['2026-04-01', 50],
]);

it('counts a planned internal share like any other', function () {
    expect(typicalBookings(internalShare: 30)->plannedShareOn(CarbonImmutable::parse('2026-02-01')))->toBe(90);
});

it('works out internal time: the planned internal share, or else what the others leave over, never below 0', function (?int $internalShare, string $day, ?int $internalTime) {
    expect(typicalBookings($internalShare)->internalTimeOn(CarbonImmutable::parse($day)))->toBe($internalTime);
})->with([
    'leftover beside one Assignment' => [null, '2026-02-01', 40],
    'leftover with nothing else' => [null, '2026-12-01', 50],
    'no leftover when the others are over 100%' => [null, '2026-03-20', 0],
    'a planned internal share, whatever is left' => [30, '2026-02-01', 30],
    'a planned internal share, even when Over-booked' => [30, '2026-03-20', 30],
    'no Internal Assignment on that day' => [null, '2025-12-31', null],
]);

it('finds the first day, from a given day on, when the planned shares go over 100%', function (string $from, ?string $firstDay) {
    expect(typicalBookings()->firstOverBookedDayFrom(CarbonImmutable::parse($from))?->toDateString())->toBe($firstDay);
})->with([
    'ahead of the overlap' => ['2026-01-10', '2026-03-15'],
    'during it' => ['2026-03-20', '2026-03-20'],
    'after it ended' => ['2026-04-01', null],
]);

it('is Over-booked only above 100%', function (int $second, ?string $firstDay) {
    $bookings = new Bookings([booking('2026-01-01', null, 40), booking('2026-01-01', null, $second)]);

    expect($bookings->firstOverBookedDayFrom(CarbonImmutable::parse('2026-01-01'))?->toDateString())->toBe($firstDay);
})->with([
    'at exactly 100%' => [60, null],
    'at 101%' => [61, '2026-01-01'],
]);

it('finds the earliest clash whatever order the Bookings come in', function () {
    $bookings = new Bookings([
        booking('2026-09-01', null, 80),
        booking('2026-06-01', '2026-06-30', 80),
        booking('2026-01-01', null, 30),
    ]);

    expect($bookings->firstOverBookedDayFrom(CarbonImmutable::parse('2026-01-01'))?->toDateString())->toBe('2026-06-01');
});

it('counts a planned internal share toward being Over-booked', function () {
    expect(typicalBookings(internalShare: 50)->firstOverBookedDayFrom(CarbonImmutable::parse('2026-01-01'))?->toDateString())->toBe('2026-01-01');
});
