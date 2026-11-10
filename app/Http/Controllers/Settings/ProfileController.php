<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the Employee's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the Employee's profile information.
     */
    public function update(ProfileUpdateRequest $request, #[CurrentUser] Employee $employee): RedirectResponse
    {
        $employee->fill($request->validated());

        if ($employee->isDirty('email')) {
            $employee->email_verified_at = null;
        }

        $employee->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the Employee's profile.
     */
    public function destroy(ProfileDeleteRequest $request, #[CurrentUser] Employee $employee): RedirectResponse
    {
        Auth::logout();

        $employee->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
