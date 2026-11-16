<?php

namespace App\Models\Builders;

use App\Enums\TravelRequestState;
use App\Models\TravelRequest;
use Carbon\CarbonInterface;

/**
 * Every reusable query on Travel Requests.
 *
 * @extends ModelBuilder<TravelRequest>
 */
class TravelRequestBuilder extends ModelBuilder
{
    /**
     * Limit the query to Travel Requests that still stand: Pending or
     * Approved.
     */
    public function standing(): static
    {
        return $this->scoped(function (): void {
            $this->whereIn('state', TravelRequestState::standing());
        });
    }

    /**
     * Limit the query to Travel Requests with at least one date from the
     * first to the last date given.
     */
    public function overlapping(CarbonInterface $first, CarbonInterface $last): static
    {
        return $this->scoped(function () use ($first, $last): void {
            $this->whereDate('first_date', '<=', $last)->whereDate('last_date', '>=', $first);
        });
    }
}
