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
    /** @var string */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * The sidebar's Team, Approvals and count all come from one read of whom the
     * Employee manages, which the Employee keeps for the page to reuse.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $employee = $request->user();
        $asManager = fn (): array => $employee instanceof Employee
            ? $employee->asManager()
            : ['managesAnyone' => false, 'hasDirectReports' => false, 'waiting' => 0];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'employee' => $employee instanceof Employee ? $employee->only('id', 'name', 'email') : null,
                'can' => fn (): array => $employee instanceof Employee ? $employee->companyWidePermissions() : [],
                'canListEmployees' => fn (): bool => $employee instanceof Employee
                    && $employee->reachesOthersThrough([Permission::ViewEmployeeRecords]),
                'canSeeWorkEntries' => fn (): bool => $employee instanceof Employee && $employee->canSeeWorkEntries(),
                'canSeeWorkEntryList' => fn (): bool => $employee instanceof Employee && $employee->can('viewList', WorkEntry::class),
                'canSeeTimeOffList' => fn (): bool => $employee instanceof Employee && $employee->can('viewList', TimeOff::class),
                'canSeeTravelRequestList' => fn (): bool => $employee instanceof Employee && $employee->can('viewList', TravelRequest::class),
                'canSeeDirectory' => fn (): bool => $asManager()['hasDirectReports']
                    || ($employee instanceof Employee && $employee->holds(Permission::ViewDirectory)),
                'canSeeTeam' => fn (): bool => $asManager()['hasDirectReports'],
                'canSeeApprovals' => fn (): bool => $asManager()['managesAnyone'],
                'approvalsWaiting' => fn (): int => $asManager()['waiting'],
                'canSeePerformanceEvaluations' => fn (): bool => $asManager()['managesAnyone']
                    || ($employee instanceof Employee && $employee->seesOthersPerformanceEvaluations()),
                'othersReach' => fn (): array => $employee instanceof Employee ? [
                    'work' => $employee->othersReachThrough(Permission::seeingWorkEntries()),
                    'timeOff' => $employee->othersReachThrough(Permission::seeingTimeOff()),
                    'travel' => $employee->othersReachThrough(Permission::seeingTravel()),
                    'performance' => $employee->othersReachThrough(Permission::seeingPerformanceEvaluations())
                        ?? ($asManager()['managesAnyone'] ? Scope::Reports : null),
                ] : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
