<?php

namespace App\Http\Requests\Travel;

use App\Enums\TravelExpenseCategory;
use App\Models\CompanySettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * A Travel Expense the signed-in Employee records or changes: a positive
 * amount in the company currency as people type it (such as "1990.00"),
 * the date it was paid, any day up to today, a Travel Expense Category, a
 * description and the receipt, a JPG, PNG or PDF of up to 10 MB. Recording
 * one always needs a receipt; changing one keeps the receipt unless a new
 * one is sent.
 */
class TravelExpenseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $decimals = CompanySettings::payCurrency()->decimals();

        return [
            'amount' => ['required', $decimals === 0 ? 'decimal:0' : "decimal:0,{$decimals}", 'gt:0', 'max:999999999'],
            'paid_on' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category' => ['required', Rule::enum(TravelExpenseCategory::class)],
            'description' => ['required', 'string', 'max:1000'],
            'receipt' => [$this->route('travelExpense') === null ? 'required' : 'nullable', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(10 * 1024)],
        ];
    }

    /**
     * The Travel Expense as it is stored, with its amount in minor units,
     * without the receipt.
     *
     * @return array{amount: int, paid_on: string, category: string, description: string}
     */
    public function travelExpense(): array
    {
        return [
            'amount' => CompanySettings::payCurrency()->toMinorUnits($this->string('amount')->value()),
            'paid_on' => $this->string('paid_on')->value(),
            'category' => $this->string('category')->value(),
            'description' => $this->string('description')->value(),
        ];
    }

    /**
     * The receipt sent, or null when a change keeps the one already kept.
     */
    public function receipt(): ?UploadedFile
    {
        $receipt = $this->file('receipt');

        return $receipt instanceof UploadedFile ? $receipt : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid_on.before_or_equal' => __('A Travel Expense can\'t be paid in the future.'),
            'receipt.required' => __('Add the receipt: every Travel Expense needs one.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'paid_on' => __('date paid'),
        ];
    }
}
