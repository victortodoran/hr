<?php

namespace App\Pages\Travel;

use App\Models\CompanySettings;
use App\Models\Employee;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Support\Currency;

/**
 * The Travel Request's page with the Add or Edit Travel Expense sheet open
 * over it (rule L14), as the props it renders.
 */
final class TravelExpenseFormPage
{
    /**
     * The Travel Request's page (TravelRequestPage) with the sheet for
     * adding a Travel Expense, the company currency and today, the latest
     * date one can be paid on.
     *
     * @return array<string, mixed>
     */
    public static function create(TravelRequest $travelRequest, Employee $actor): array
    {
        return [
            ...TravelRequestPage::props($travelRequest, $actor),
            'expenseSheet' => 'add',
            ...self::shared(CompanySettings::payCurrency()),
        ];
    }

    /**
     * The Travel Request's page (TravelRequestPage) with the sheet for
     * editing a Travel Expense, filled in as it would be
     * typed, with its receipt's file name, the receipt kept unless a new file is chosen; for a
     * Declined one, who declined it and why, and the effective Manager it
     * goes back to as Pending once saved, or null when nobody is left to
     * decide and saving Approves it at once.
     *
     * @return array<string, mixed>
     */
    public static function edit(TravelRequest $travelRequest, TravelExpense $travelExpense, Employee $actor): array
    {
        $currency = CompanySettings::payCurrency();

        return [
            ...TravelRequestPage::props($travelRequest, $actor),
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
            'manager' => Employee::query()->withEffectiveManager()->with('effectiveManager:id,name')->findOrFail($actor->id)->effectiveManager?->name,
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
