<?php

namespace App\Http\Controllers\Employees;

use App\Enums\DirectoryTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\DirectoryListRequest;
use App\Models\Employee;
use App\Pages\Employees\DirectoryIndexPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryController extends Controller
{
    public function index(#[CurrentUser] Employee $actor, DirectoryListRequest $request): Response
    {
        $tabs = DirectoryTab::openableBy($actor);

        return Inertia::render('directory/Index', DirectoryIndexPage::props($actor, $request->tab($tabs), $tabs, $request->search(), $request->person()));
    }

    /** Former Employees have left the Directory, so an old link to one finds nothing. */
    public function show(int $employee): RedirectResponse
    {
        $person = Employee::query()->notFormer()->findOrFail($employee);

        return to_route('directory.index', ['person' => $person->id]);
    }
}
