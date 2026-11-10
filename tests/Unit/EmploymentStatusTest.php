<?php

use App\Enums\EmploymentStatus;
use Illuminate\Support\Carbon;

covers(EmploymentStatus::class);

it('works out whether an Employee is future, current or former on a day', function (string $day, ?string $leavingDate, EmploymentStatus $status) {
    expect(EmploymentStatus::on(Carbon::parse($day), Carbon::parse('2026-03-02'), $leavingDate === null ? null : Carbon::parse($leavingDate)))
        ->toBe($status);
})->with([
    'the day before the Start Date' => ['2026-03-01', null, EmploymentStatus::Future],
    'on the Start Date' => ['2026-03-02', null, EmploymentStatus::Current],
    'long after, with no Leaving Date' => ['2030-01-01', null, EmploymentStatus::Current],
    'on the Leaving Date' => ['2026-06-30', '2026-06-30', EmploymentStatus::Current],
    'the day after the Leaving Date' => ['2026-07-01', '2026-06-30', EmploymentStatus::Former],
    'leaving on the Start Date, that same day' => ['2026-03-02', '2026-03-02', EmploymentStatus::Current],
]);
