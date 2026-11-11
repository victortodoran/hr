<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * An Employee's Bookings, to work out how much of their time is planned on a
 * day. They are Over-booked on a day when their planned shares add up to more
 * than 100%. An Internal Assignment with no planned share counts as nothing
 * towards that; its time is whatever the others leave over.
 */
final readonly class Bookings
{
    /**
     * @param  array<int, Booking>  $bookings
     */
    public function __construct(private array $bookings) {}

    /**
     * The total of the shares planned on the day, as a whole percentage.
     */
    public function plannedShareOn(CarbonInterface $day): int
    {
        return array_sum(array_map(
            fn (Booking $booking): int => $booking->share ?? 0,
            $this->runningOn($day),
        ));
    }

    /**
     * The Employee's internal time on the day: the planned internal share,
     * or else 100% minus the other shares, never below 0. Null when they have
     * no Internal Assignment that day, so before they start or after they
     * leave.
     */
    public function internalTimeOn(CarbonInterface $day): ?int
    {
        $running = $this->runningOn($day);
        $internal = array_find($running, fn (Booking $booking): bool => $booking->isInternal);

        if ($internal === null) {
            return null;
        }

        return $internal->share ?? max(0, 100 - $this->plannedShareOn($day));
    }

    /**
     * The first day, from the given one on, when the Employee is Over-booked,
     * or null when they never are. The total only rises when a Booking
     * starts, so only the given day and later start dates need checking.
     */
    public function firstOverBookedDayFrom(CarbonInterface $from): ?CarbonInterface
    {
        $candidates = [$from->copy()->startOfDay()];

        foreach ($this->bookings as $booking) {
            if ($booking->start->isAfter($from)) {
                $candidates[] = $booking->start;
            }
        }

        usort($candidates, fn (CarbonInterface $a, CarbonInterface $b): int => $a <=> $b);

        return array_find($candidates, fn (CarbonInterface $day): bool => $this->plannedShareOn($day) > 100);
    }

    /**
     * @return array<int, Booking>
     */
    private function runningOn(CarbonInterface $day): array
    {
        return array_filter($this->bookings, fn (Booking $booking): bool => $booking->runsOn($day));
    }
}
