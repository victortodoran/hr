<?php

namespace App\Rules;

use App\Models\Employee;
use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Keeps an Employee's or a Project's dates around its Assignments: the start
 * can't be later than the first Assignment starts, and the end can't be
 * earlier than the last one starts, since every Assignment falls within
 * those dates. With no owner yet (when adding one), anything fits.
 */
class AssignmentsFit implements ValidationRule
{
    /**
     * @param  string  $message  Explains a refusal, with :name and :date
     */
    private function __construct(
        private Employee|Project|null $owner,
        private bool $isStart,
        private string $message,
    ) {}

    /**
     * A start date no later than the owner's first Assignment starts.
     */
    public static function startOf(Employee|Project|null $owner, string $message): self
    {
        return new self($owner, isStart: true, message: $message);
    }

    /**
     * An end date no earlier than the owner's last Assignment starts.
     */
    public static function endOf(Employee|Project|null $owner, string $message): self
    {
        return new self($owner, isStart: false, message: $message);
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->owner === null) {
            return;
        }

        $assignments = $this->owner->assignments();
        $edge = $this->isStart ? $assignments->min('start_date') : $assignments->max('start_date');

        if (! is_string($edge)) {
            return;
        }

        $edge = Carbon::parse($edge);
        $date = Carbon::parse($value);

        if ($this->isStart ? $edge->isBefore($date) : $edge->isAfter($date)) {
            $fail(__($this->message, ['name' => $this->owner->name, 'date' => $edge->format('j M Y')]));
        }
    }
}
