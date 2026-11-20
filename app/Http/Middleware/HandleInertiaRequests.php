<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\TimeOff;
use App\Models\TravelRequest;
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
     * Define the props that are shared by default. The sidebar's Team,
     * Approvals and count all come from one read of whom the Employee
     * manages, made at most once per request.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $employee = $request->user();
        $asManager = null;
        $asManagerOnce = function () use ($employee, &$asManager): array {
            return $asManager ??= $employee instanceof Employee
                ? $employee->asManager()
                : ['managesAnyone' => false, 'hasDirectReports' => false, 'waiting' => 0];
        };

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'employee' => $employee,
                'can' => fn (): array => $employee instanceof Employee ? $employee->companyWidePermissions() : [],
                'canListEmployees' => fn (): bool => $employee instanceof Employee
                    && $employee->reachesOthersThrough([Permission::ViewEmployeeRecords]),
                'canSeeWorkEntries' => fn (): bool => $employee instanceof Employee && $employee->canSeeWorkEntries(),
                'canSeeWorkEntryList' => fn (): bool => $employee instanceof Employee && $employee->can('viewList', WorkEntry::class),
                'canSeeTimeOffList' => fn (): bool => $employee instanceof Employee && $employee->can('viewList', TimeOff::class),
                'canSeeTravelRequestList' => fn (): bool => $employee instanceof Employee && $employee->can('viewList', TravelRequest::class),
                'canSeeDirectory' => fn (): bool => $asManagerOnce()['hasDirectReports']
                    || ($employee instanceof Employee && $employee->holds(Permission::ViewDirectory)),
                'canSeeTeam' => fn (): bool => $asManagerOnce()['hasDirectReports'],
                'canSeeApprovals' => fn (): bool => $asManagerOnce()['managesAnyone'],
                'approvalsWaiting' => fn (): int => $asManagerOnce()['waiting'],
                'canSeePerformanceEvaluations' => fn (): bool => $asManagerOnce()['managesAnyone']
                    || ($employee instanceof Employee && $employee->seesOthersPerformanceEvaluations()),
                'othersReach' => fn (): array => $employee instanceof Employee ? [
                    'work' => $employee->othersReachThrough(Permission::seeingWorkEntries()),
                    'timeOff' => $employee->othersReachThrough(Permission::seeingTimeOff()),
                    'travel' => $employee->othersReachThrough(Permission::seeingTravel()),
                    'performance' => $employee->othersReachThrough(Permission::seeingPerformanceEvaluations())
                        ?? ($asManagerOnce()['managesAnyone'] ? Scope::Reports : null),
                ] : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
