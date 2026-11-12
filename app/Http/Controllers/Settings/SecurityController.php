<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the Employee's security settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Update the Employee's password.
     */
    public function update(PasswordUpdateRequest $request, #[CurrentUser] Employee $employee): RedirectResponse
    {
        $employee->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
