<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\WorkEntry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $employee = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'employee' => $employee,
                'can' => fn (): array => $employee instanceof Employee ? $employee->companyWidePermissions() : [],
                'canSeeOwnPage' => fn (): bool => $employee instanceof Employee && $employee->can('view', $employee),
                'canListEmployees' => fn (): bool => $employee instanceof Employee
                    && ($employee->widestScopeFor(Permission::ViewEmployeeRecords)?->coversOthers() ?? false),
                'canSeeWorkEntries' => fn (): bool => $employee instanceof Employee
                    && ($employee->holds(Permission::ViewWorkEntries) || $employee->holds(Permission::ManageWorkEntries)),
                'canSeeApprovals' => fn (): bool => $employee instanceof Employee && $employee->can('viewApprovals', WorkEntry::class),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
