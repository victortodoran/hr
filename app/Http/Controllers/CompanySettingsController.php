<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanySettingsRequest;
use App\Models\CompanySettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets those who manage the company change its name, and its currency until
 * any Pay is recorded in it.
 */
class CompanySettingsController extends Controller
{
    /**
     * Show the company settings, and whether the currency is locked by Pay.
     */
    public function edit(): Response
    {
        return Inertia::render('company/Edit', [
            'company' => CompanySettings::query()->sole()->only('name', 'currency'),
            'isCurrencyLocked' => CompanySettings::isCurrencyLocked(),
        ]);
    }

    /**
     * Save the company's name and currency.
     */
    public function update(CompanySettingsRequest $request): RedirectResponse
    {
        CompanySettings::query()->sole()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company settings saved.')]);

        return to_route('company.edit');
    }
}
