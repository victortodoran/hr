<?php

namespace App\Rules;

use App\Models\Employee;
use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/** The Internal Assignment follows the Employee's dates, so it never stands in the way. */
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

    public static function startOf(Employee|Project|null $owner, string $message): self
    {
        return new self($owner, isStart: true, message: $message);
    }

    public static function endOf(Employee|Project|null $owner, string $message): self
    {
        return new self($owner, isStart: false, message: $message);
    }

    /** @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->owner === null) {
            return;
        }

        $assignments = $this->owner->assignments()->notInternal();
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
