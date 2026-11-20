<?php

namespace App\Http\Controllers\Company;

use App\Actions\Company\ChangeCompanySettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CompanySettingsRequest;
use App\Pages\Company\CompanySettingsPage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('company/Edit', CompanySettingsPage::edit());
    }

    public function update(CompanySettingsRequest $request, ChangeCompanySettings $changeCompanySettings): RedirectResponse
    {
        /** @var array{name: string, currency: string} $settings */
        $settings = $request->validated();

        $changeCompanySettings->change($settings);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company settings saved.')]);

        return to_route('company.edit');
    }
}
