<?php

namespace App\Models\Builders;

use App\Enums\TimeOffState;
use App\Models\TimeOff;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every reusable query on Time Off.
 *
 * @extends Builder<TimeOff>
 */
class TimeOffBuilder extends Builder
{
    /**
     * Limit the query to Time Off that still stands: Pending or Approved.
     */
    public function standing(): static
    {
        $this->whereIn('state', TimeOffState::standing());

        return $this;
    }

    /**
     * Limit the query to Time Off with at least one date from the first to
     * the last date given.
     */
    public function overlapping(CarbonInterface $first, CarbonInterface $last): static
    {
        $this->whereDate('first_date', '<=', $last)->whereDate('last_date', '>=', $first);

        return $this;
    }
}
