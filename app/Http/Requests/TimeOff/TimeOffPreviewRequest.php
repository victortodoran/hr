<?php

namespace App\Http\Requests\TimeOff;

use App\Models\Employee;
use App\Models\TimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Enough to preview the days it would take; requesting it checks the rest. */
class TimeOffPreviewRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'last_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:first_date'],
            'is_paid' => ['required', 'boolean'],
        ];
    }

    /** @return array{first_date: string, last_date: string, is_paid: bool} */
    public function timeOff(): array
    {
        return [
            'first_date' => $this->string('first_date')->value(),
            'last_date' => $this->string('last_date')->value(),
            'is_paid' => $this->boolean('is_paid'),
        ];
    }

    public function unsavedTimeOff(): TimeOff
    {
        return new TimeOff($this->timeOff());
    }

    public function employee(): Employee
    {
        /** @var Employee */
        return $this->user();
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkLength($validator)];
    }

    /** Counting both ends, Time Off from 14 Dec 2026 may last until 13 Dec 2027, and from 29 Feb until 27 Feb. */
    private function checkLength(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($this->lastDate()->greaterThanOrEqualTo($this->firstDate()->addYearNoOverflow())) {
            $validator->errors()->add('last_date', __('The longest Time Off is a year. Request longer leave as several Time Off.'));
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
