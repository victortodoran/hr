<?php

namespace App\Rules;

use App\Models\TravelRequest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Keeps dates around the Pending and Approved Travel Requests they hold, so
 * none ever has days outside its Assignment: a start can't be later than
 * the first day of travel, and an end can't be earlier than the last.
 * Ending an Employee or a Project cuts its Assignments off, so their ends
 * count too. Declined and Cancelled Travel Requests never block. With
 * nothing to hold yet (when adding one), anything fits.
 */
class TravelRequestsFit implements ValidationRule
{
    /**
     * @param  HasMany<TravelRequest, *>|HasManyThrough<TravelRequest, *, *>|null  $travelRequests
     * @param  string  $message  Explains a refusal, with :name for the Employee, :project for the Assignment's Project and :date for the first or last day of travel
     */
    private function __construct(
        private HasMany|HasManyThrough|null $travelRequests,
        private bool $isStart,
        private string $message,
    ) {}

    /**
     * A start no later than the first day of the standing Travel Requests.
     *
     * @param  HasMany<TravelRequest, *>|HasManyThrough<TravelRequest, *, *>|null  $travelRequests
     */
    public static function startOf(HasMany|HasManyThrough|null $travelRequests, string $message): self
    {
        return new self($travelRequests, isStart: true, message: $message);
    }

    /**
     * An end no earlier than the last day of the standing Travel Requests.
     *
     * @param  HasMany<TravelRequest, *>|HasManyThrough<TravelRequest, *, *>|null  $travelRequests
     */
    public static function endOf(HasMany|HasManyThrough|null $travelRequests, string $message): self
    {
        return new self($travelRequests, isStart: false, message: $message);
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->travelRequests === null) {
            return;
        }

        $date = Carbon::parse($value);
        $outside = $this->travelRequests
            ->standing()
            ->with(['employee:id,name', 'assignment.project:id,name'])
            ->when(
                $this->isStart,
                fn (Builder $travel) => $travel->whereDate('travel_requests.first_date', '<', $date)->orderBy('travel_requests.first_date'),
                fn (Builder $travel) => $travel->whereDate('travel_requests.last_date', '>', $date)->orderByDesc('travel_requests.last_date'),
            )
            ->first();

        if ($outside instanceof TravelRequest) {
            $fail(__($this->message, [
                'name' => $outside->employee->name,
                'project' => $outside->assignment->project->name,
                'date' => ($this->isStart ? $outside->first_date : $outside->last_date)->format('j M Y'),
            ]));
        }
    }
}
