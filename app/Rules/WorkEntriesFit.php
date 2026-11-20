<?php

namespace App\Rules;

use App\Models\WorkEntry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/** Ending an Employee or a Project cuts its Assignments off, so their ends count too. */
class WorkEntriesFit implements ValidationRule
{
    /**
     * @param  HasMany<WorkEntry, *>|HasManyThrough<WorkEntry, *, *>|null  $workEntries
     * @param  string  $message  Explains a refusal, with :date
     */
    private function __construct(
        private HasMany|HasManyThrough|null $workEntries,
        private bool $isStart,
        private string $message,
    ) {}

    /** @param  HasMany<WorkEntry, *>|HasManyThrough<WorkEntry, *, *>|null  $workEntries */
    public static function startOf(HasMany|HasManyThrough|null $workEntries, string $message): self
    {
        return new self($workEntries, isStart: true, message: $message);
    }

    /** @param  HasMany<WorkEntry, *>|HasManyThrough<WorkEntry, *, *>|null  $workEntries */
    public static function endOf(HasMany|HasManyThrough|null $workEntries, string $message): self
    {
        return new self($workEntries, isStart: false, message: $message);
    }

    /** @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->workEntries === null) {
            return;
        }

        $edge = $this->isStart ? $this->workEntries->min('date') : $this->workEntries->max('date');

        if (! is_string($edge)) {
            return;
        }

        $edge = Carbon::parse($edge);
        $date = Carbon::parse($value);

        if ($this->isStart ? $edge->isBefore($date) : $edge->isAfter($date)) {
            $fail(str_replace(':date', $edge->format('j M Y'), $this->message));
        }
    }
}
