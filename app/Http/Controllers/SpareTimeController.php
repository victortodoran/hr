<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shows who has spare time on a day, to help staff new Projects: the
 * Employees whose Assignments the viewer may see and whose internal time
 * that day is above 0%. Nobody has internal time outside their period of
 * work, so Future and Former Employees drop out on their own.
 */
class SpareTimeController extends Controller
{
    /**
     * List those Employees by name, with their internal Job Title and
     * internal time, on the chosen day or else today.
     */
    public function index(Request $request, #[CurrentUser] Employee $actor): Response
    {
        $day = Carbon::parse($request->validate(['day' => ['nullable', 'date']])['day'] ?? today());

        $employees = Employee::query()
            ->whoseAssignmentsAreVisibleTo($actor)
            ->with(['assignments.project:id,is_internal', 'internalAssignment:id,employee_id,project_id,job_title']) // @pest-mutate-ignore (eager loading only saves queries)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $employee): array => [
                ...$employee->asListed(),
                'job_title' => $employee->internalAssignment?->job_title,
                'internal_time' => $employee->bookings()->internalTimeOn($day),
            ])
            ->filter(fn (array $employee): bool => $employee['internal_time'] > 0)
            ->values();

        return Inertia::render('spare-time/Index', [
            'day' => $day->toDateString(),
            'employees' => $employees,
        ]);
    }
}
