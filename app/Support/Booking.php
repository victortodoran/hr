<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * One Assignment as booking maths sees it: its dates, its planned share of
 * time and whether it is the Internal Assignment. An Internal Assignment with
 * no planned share has a null share.
 */
final readonly class Booking
{
    public function __construct(
        public CarbonInterface $start,
        public ?CarbonInterface $end,
        public ?int $share,
        public bool $isInternal,
    ) {}

    /**
     * Whether the Booking runs on the day; its end date is included.
     */
    public function runsOn(CarbonInterface $day): bool
    {
        return ! $this->start->isAfter($day) && ($this->end === null || ! $this->end->isBefore($day));
    }
}
