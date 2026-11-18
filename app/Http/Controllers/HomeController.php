<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Pages\HomePage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home, where every Employee lands after signing in: a summary of their
 * hours this month and this year, their Time Off and what waits for them to
 * decide. Nothing is logged or decided here.
 */
class HomeController extends Controller
{
    /**
     * Show the signed-in Employee's summary.
     */
    public function __invoke(#[CurrentUser] Employee $employee): Response
    {
        return Inertia::render('Home', HomePage::props($employee));
    }
}
