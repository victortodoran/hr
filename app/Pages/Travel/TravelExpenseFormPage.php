<?php

namespace App\Pages\Travel;

use App\Models\CompanySettings;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
use App\Support\Currency;
use App\Support\TravelHistory;

/**
 * The Travel Expense forms as the props they render: recording one on a
 * Travel Request, and changing one, each leading back to the Travel
 * Request's page.
 */
final class TravelExpenseFormPage
{
    /**
     * The form for recording a Travel Expense on the Travel Request, named
     * at its top, with the company currency and today, the latest date one
     * can be paid on.
     *
     * @return array{travelRequest: array{id: int, destination: string, first_date: string, last_date: string}, currency: array{code: string, decimals: int}, today: string}
     */
    public static function create(TravelRequest $travelRequest): array
    {
        return self::shared($travelRequest, CompanySettings::payCurrency());
    }

    /**
     * The form for changing a Travel Expense, filled in as it would be
     * typed; its receipt is kept unless a new file is chosen.
     *
     * @return array{travelRequest: array{id: int, destination: string, first_date: string, last_date: string}, currency: array{code: string, decimals: int}, today: string, travelExpense: array{id: int, paid_on: string, category: string, amount: string, description: string}}
     */
    public static function edit(TravelRequest $travelRequest, TravelExpense $travelExpense): array
    {
        $currency = CompanySettings::payCurrency();

        return [
            ...self::shared($travelRequest, $currency),
            'travelExpense' => [
                'id' => $travelExpense->id,
                'paid_on' => $travelExpense->paid_on->toDateString(),
                'category' => $travelExpense->category->value,
                'amount' => $currency->toAmount($travelExpense->amount),
                'description' => $travelExpense->description,
            ],
        ];
    }

    /**
     * What both Travel Expense forms carry: the Travel Request it is on,
     * the company currency, and today.
     *
     * @return array{travelRequest: array{id: int, destination: string, first_date: string, last_date: string}, currency: array{code: string, decimals: int}, today: string}
     */
    private static function shared(TravelRequest $travelRequest, Currency $currency): array
    {
        return [
            'travelRequest' => TravelHistory::summary($travelRequest),
            'currency' => ['code' => $currency->code, 'decimals' => $currency->decimals()],
            'today' => today()->toDateString(),
        ];
    }
}
