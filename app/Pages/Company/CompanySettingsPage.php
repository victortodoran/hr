<?php

namespace App\Pages\Company;

use App\Models\CompanySettings;

final class CompanySettingsPage
{
    /** @return array{company: array<string, mixed>, isCurrencyLocked: bool} */
    public static function edit(): array
    {
        return [
            'company' => CompanySettings::query()->sole()->only('name', 'currency'),
            'isCurrencyLocked' => CompanySettings::isCurrencyLocked(),
        ];
    }
}
