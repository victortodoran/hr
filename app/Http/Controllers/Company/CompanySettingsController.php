<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CompanySettingsRequest;
use App\Models\CompanySettings;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
     * Save the company's name and currency. The Internal Project is named
     * after the company, so it is renamed in the same save.
     */
    public function update(CompanySettingsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            CompanySettings::query()->sole()->update($request->validated());
            Project::query()->where('is_internal', true)->update(['name' => $request->string('name')->value()]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company settings saved.')]);

        return to_route('company.edit');
    }
}
