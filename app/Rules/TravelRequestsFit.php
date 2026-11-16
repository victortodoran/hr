<?php

namespace App\Rules;

use App\Models\TravelRequest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Keeps an end date after the Pending and Approved Travel Requests it
 * holds, so none ever has days after its Assignment ends: ending an
 * Assignment, an Employee or a Project cuts its Assignments off, so the
 * end can't be earlier than the last day of travel on them. Declined and
 * Cancelled Travel Requests never block. With nothing to hold yet (when
 * adding one), anything fits.
 */
class TravelRequestsFit implements ValidationRule
{
    /**
     * @param  HasMany<TravelRequest, *>|HasManyThrough<TravelRequest, *, *>|null  $travelRequests
     * @param  string  $message  Explains a refusal, with :name for the Employee, :project for the Assignment's Project and :date for the last day of travel
     */
    private function __construct(
        private HasMany|HasManyThrough|null $travelRequests,
        private string $message,
    ) {}

    /**
     * An end no earlier than the last day of the standing Travel Requests.
     *
     * @param  HasMany<TravelRequest, *>|HasManyThrough<TravelRequest, *, *>|null  $travelRequests
     */
    public static function endOf(HasMany|HasManyThrough|null $travelRequests, string $message): self
    {
        return new self($travelRequests, $message);
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->travelRequests === null) {
            return;
        }

        $end = Carbon::parse($value);
        $latest = $this->travelRequests
            ->standing()
            ->whereDate('travel_requests.last_date', '>', $end)
            ->with(['employee:id,name', 'assignment.project:id,name'])
            ->orderByDesc('travel_requests.last_date')
            ->first();

        if ($latest instanceof TravelRequest) {
            $fail(__($this->message, [
                'name' => $latest->employee->name,
                'project' => $latest->assignment->project->name,
                'date' => $latest->last_date->format('j M Y'),
            ]));
        }
    }
}
