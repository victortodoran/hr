<?php

namespace App\Actions;

use App\Models\CompanySettings;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Saves the company's name and currency. The Internal Project is named
 * after the company, so it is renamed in the same transaction.
 */
class ChangeCompanySettings
{
    /**
     * @param  array{name: string, currency: string}  $settings
     */
    public function change(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            CompanySettings::query()->sole()->update($settings);
            Project::query()->internal()->update(['name' => $settings['name']]);
        });
    }
}
