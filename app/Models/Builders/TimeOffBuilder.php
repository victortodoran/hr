<?php

namespace App\Models\Builders;

use App\Enums\TimeOffState;
use App\Models\TimeOff;
use Carbon\CarbonInterface;

/**
 * Every reusable query on Time Off.
 *
 * @extends ModelBuilder<TimeOff>
 */
class TimeOffBuilder extends ModelBuilder
{
    /**
     * Limit the query to Time Off that still stands: Pending or Approved.
     */
    public function standing(): static
    {
        return $this->scoped(function (): void {
            $this->whereIn('state', TimeOffState::standing());
        });
    }

    /**
     * Limit the query to Time Off with at least one date from the first to
     * the last date given.
     */
    public function overlapping(CarbonInterface $first, CarbonInterface $last): static
    {
        return $this->scoped(function () use ($first, $last): void {
            $this->whereDate('first_date', '<=', $last)->whereDate('last_date', '>=', $first);
        });
    }
}
