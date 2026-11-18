<?php

namespace App\Pages\Company;

use App\Models\CompanySettings;

/**
 * The company settings form as the props it renders.
 */
final class CompanySettingsPage
{
    /**
     * The company's name and currency, and whether the currency is locked
     * by Pay or Travel Expenses.
     *
     * @return array{company: array<string, mixed>, isCurrencyLocked: bool}
     */
    public static function edit(): array
    {
        return [
            'company' => CompanySettings::query()->sole()->only('name', 'currency'),
            'isCurrencyLocked' => CompanySettings::isCurrencyLocked(),
        ];
    }
}
