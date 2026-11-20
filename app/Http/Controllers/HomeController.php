<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Pages\HomePage;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(#[CurrentUser] Employee $employee): Response
    {
        return Inertia::render('Home', HomePage::props($employee));
    }
}
