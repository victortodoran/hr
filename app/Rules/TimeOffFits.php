<?php

namespace App\Rules;

use App\Models\TimeOff;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Keeps an Employee's dates around their Pending and Approved Time Off, so
 * none ever has days outside the employment: a Start Date can't be later
 * than the first day of Time Off, and a Leaving Date can't be earlier than
 * the last. Declined and Cancelled Time Off never blocks. With no Employee
 * yet (when adding one), anything fits.
 */
class TimeOffFits implements ValidationRule
{
    /**
     * @param  HasMany<TimeOff, *>|null  $timeOff
     * @param  string  $message  Explains a refusal, with :name for the Employee and :first and :last for the days of Time Off in the way
     */
    private function __construct(
        private ?HasMany $timeOff,
        private bool $isStart,
        private string $message,
    ) {}

    /**
     * A start no later than the first day of the standing Time Off.
     *
     * @param  HasMany<TimeOff, *>|null  $timeOff
     */
    public static function startOf(?HasMany $timeOff, string $message): self
    {
        return new self($timeOff, isStart: true, message: $message);
    }

    /**
     * An end no earlier than the last day of the standing Time Off.
     *
     * @param  HasMany<TimeOff, *>|null  $timeOff
     */
    public static function endOf(?HasMany $timeOff, string $message): self
    {
        return new self($timeOff, isStart: false, message: $message);
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->timeOff === null) {
            return;
        }

        $date = Carbon::parse($value);
        $outside = $this->timeOff
            ->standing()
            ->with('employee:id,name')
            ->when(
                $this->isStart,
                fn (Builder $timeOff) => $timeOff->whereDate('first_date', '<', $date)->orderBy('first_date'),
                fn (Builder $timeOff) => $timeOff->whereDate('last_date', '>', $date)->orderByDesc('last_date'),
            )
            ->first();

        if ($outside instanceof TimeOff) {
            $fail(__($this->message, [
                'name' => $outside->employee->name,
                'first' => $outside->first_date->format('j M Y'),
                'last' => $outside->last_date->format('j M Y'),
            ]));
        }
    }
}
