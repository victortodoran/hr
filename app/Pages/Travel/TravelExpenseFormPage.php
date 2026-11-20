<?php

namespace App\Pages\Travel;

use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Support\Currency;

/**
 * The Add and Edit Travel Expense sheet as the props it adds to the
 * Travel Request's page it opens over (rule L14).
 */
final class TravelExpenseFormPage
{
    /**
     * The sheet for adding a Travel Expense, with the company currency and
     * today, the latest date one can be paid on.
     *
     * @return array{expenseSheet: 'add', currency: array{code: string, decimals: int}, today: string}
     */
    public static function create(): array
    {
        return [
            'expenseSheet' => 'add',
            ...self::shared(CompanySettings::payCurrency()),
        ];
    }

    /**
     * The sheet for editing a Travel Expense, filled in as it would be
     * typed, with its receipt's file name, the receipt kept unless a new file is chosen; for a
     * Declined one, who declined it and why, and the Manager it goes back
     * to as Pending once saved.
     *
     * @return array{expenseSheet: 'edit', currency: array{code: string, decimals: int}, today: string, travelExpense: array{id: int, paid_on: string, category: string, amount: string, description: string, state: string, decline_reason: string|null, declined_by: string|null, receipt_name: string}, manager: string|null}
     */
    public static function edit(TravelExpense $travelExpense, Employee $actor): array
    {
        $currency = CompanySettings::payCurrency();

        return [
            'expenseSheet' => 'edit',
            ...self::shared($currency),
            'travelExpense' => [
                'id' => $travelExpense->id,
                'paid_on' => $travelExpense->paid_on->toDateString(),
                'category' => $travelExpense->category->value,
                'amount' => $currency->toAmount($travelExpense->amount),
                'description' => $travelExpense->description,
                'state' => $travelExpense->state->value,
                'decline_reason' => $travelExpense->decline_reason,
                'declined_by' => $travelExpense->decliner?->name,
                'receipt_name' => 'Receipt.'.strtolower(pathinfo($travelExpense->receipt_path, PATHINFO_EXTENSION)),
            ],
            'manager' => $actor->manager?->name,
        ];
    }

    /**
     * What both sheets carry: the company currency and today.
     *
     * @return array{currency: array{code: string, decimals: int}, today: string}
     */
    private static function shared(Currency $currency): array
    {
        return [
            'currency' => ['code' => $currency->code, 'decimals' => $currency->decimals()],
            'today' => today()->toDateString(),
        ];
    }
}
