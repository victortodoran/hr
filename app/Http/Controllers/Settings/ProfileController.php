<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Pages\Settings\ProfilePage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the Employee's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', ProfilePage::props($request->user(), $request->session()->get('status')));
    }
}
