<?php

namespace App\Http\Requests\Assignments;

use App\Enums\PayKind;
use App\Models\Assignment;
use App\Models\CompanySettings;
use App\Models\PayEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * A new entry in an Assignment's Pay history: its kind, its amount in the
 * company currency as people type it (such as "4500.50"), and the day it
 * takes effect, which must fall while the Assignment runs and may be ahead.
 */
class PayEntryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $decimals = CompanySettings::payCurrency()->decimals();
        $assignment = $this->assignment();

        return [
            'kind' => ['required', Rule::enum(PayKind::class)],
            'amount' => ['required', $decimals === 0 ? 'decimal:0' : "decimal:0,{$decimals}", 'gt:0', 'max:999999999'],
            'effective_from' => [
                'required',
                'date',
                'after_or_equal:'.$assignment->start_date->toDateString(),
                ...($assignment->end_date === null ? [] : ['before_or_equal:'.$assignment->end_date->toDateString()]),
                Rule::unique(PayEntry::class)->where('assignment_id', $assignment->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $assignment = $this->assignment();
        $outsideTheAssignment = $assignment->end_date === null
            ? __('Pay must take effect while the Assignment runs, from :start with no end date.', ['start' => $assignment->start_date->toDateString()])
            : __('Pay must take effect while the Assignment runs, from :start to :end.', ['start' => $assignment->start_date->toDateString(), 'end' => $assignment->end_date->toDateString()]);

        return [
            'effective_from.after_or_equal' => $outsideTheAssignment,
            'effective_from.before_or_equal' => $outsideTheAssignment,
            'effective_from.unique' => __('This Assignment already has Pay taking effect on that day.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'effective_from' => __('effective date'),
        ];
    }

    /**
     * The entry to record, with its amount in minor units.
     *
     * @return array{kind: string, amount: int, effective_from: string}
     */
    public function payEntry(): array
    {
        return [
            'kind' => $this->string('kind')->value(),
            'amount' => CompanySettings::payCurrency()->toMinorUnits($this->string('amount')->value()),
            'effective_from' => $this->string('effective_from')->value(),
        ];
    }

    /**
     * The Assignment the Pay is for.
     */
    private function assignment(): Assignment
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof Assignment ? $assignment : throw new LogicException('Pay is always added to an Assignment.'); // @pest-mutate-ignore (the route always binds an Assignment)
    }
}
