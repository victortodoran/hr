<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\TimeOff;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Time Off the signed-in Employee is about to request for themselves:
 * whole days from a first to a last date, at most a year apart, Paid or
 * Unpaid, starting today or later. It is enough to preview the days it would take; requesting it
 * checks the rest.
 */
class TimeOffPreviewRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'last_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:first_date', $this->atMostAYear(...)],
            'is_paid' => ['required', 'boolean'],
        ];
    }

    /**
     * The Time Off as it is stored.
     *
     * @return array{first_date: string, last_date: string, is_paid: bool}
     */
    public function timeOff(): array
    {
        return [
            'first_date' => $this->string('first_date')->value(),
            'last_date' => $this->string('last_date')->value(),
            'is_paid' => $this->boolean('is_paid'),
        ];
    }

    /**
     * The Time Off as an unsaved model, to count its days.
     */
    public function unsavedTimeOff(): TimeOff
    {
        return new TimeOff($this->timeOff());
    }

    /**
     * The Employee requesting the Time Off: always the one signed in.
     */
    public function employee(): Employee
    {
        /** @var Employee */
        return $this->user();
    }

    /**
     * Refuse a Last Date more than a year after the First Date; longer leave
     * is several Time Off. Runs only once both dates are known to be valid.
     *
     * @param  Closure(string): mixed  $fail
     */
    private function atMostAYear(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->lastDate()->greaterThan($this->firstDate()->addYear())) {
            $fail(__('The longest Time Off is a year. Request longer leave as several Time Off.'));
        }
    }

    protected function firstDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('first_date')->value());
    }

    protected function lastDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('last_date')->value());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_date.after_or_equal' => __('Time Off can\'t start in the past.'),
            'is_paid.required' => __('Choose Paid or Unpaid Time Off.'),
            'is_paid.boolean' => __('Choose Paid or Unpaid Time Off.'),
        ];
    }
}
