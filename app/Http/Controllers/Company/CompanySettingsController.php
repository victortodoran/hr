<?php

namespace App\Http\Controllers\Company;

use App\Actions\ChangeCompanySettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CompanySettingsRequest;
use App\Pages\Company\CompanySettingsPage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets those who manage the company change its name, and its currency until
 * any Pay or Travel Expense is recorded in it.
 */
class CompanySettingsController extends Controller
{
    /**
     * Show the company settings, and whether the currency is locked by Pay.
     */
    public function edit(): Response
    {
        return Inertia::render('company/Edit', CompanySettingsPage::edit());
    }

    /**
     * Save the company's name and currency, renaming the Internal Project
     * with it.
     */
    public function update(CompanySettingsRequest $request, ChangeCompanySettings $changeCompanySettings): RedirectResponse
    {
        /** @var array{name: string, currency: string} $settings */
        $settings = $request->validated();

        $changeCompanySettings->change($settings);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company settings saved.')]);

        return to_route('company.edit');
    }
}
