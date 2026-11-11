<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets every Employee with view directory find colleagues and see where
 * anyone sits in the company. It never shows Assignments, dates or Pay,
 * though it links to someone's Employee page for viewers allowed to open it.
 */
class DirectoryController extends Controller
{
    /**
     * Show current Employees by name, with their internal Job Title,
     * optionally searched by part of a name.
     */
    public function index(Request $request): Response
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:255']])['search'] ?? '';

        $employees = Employee::query()
            ->current()
            ->withEffectiveManager()
            ->with(['department:id,name', 'effectiveManager:id,name', 'internalAssignment:id,employee_id,project_id,job_title'])
            ->whereLike('name', '%'.addcslashes($search, '\\%_').'%')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'job_title' => $employee->internalAssignment?->job_title,
                'department' => $employee->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
                'manager' => $employee->effectiveManager?->only('id', 'name'),
            ]);

        return Inertia::render('directory/Index', [
            'employees' => $employees,
            'search' => $search,
        ]);
    }

    /**
     * Show an Employee's Reporting Chain, from them up to the top Employee.
     * Former Employees have left the directory, so they have no page.
     */
    public function show(#[CurrentUser] Employee $actor, Employee $employee): Response
    {
        abort_if($employee->employmentStatus() === EmploymentStatus::Former, 404);

        return Inertia::render('directory/Show', [
            'chain' => collect([$employee->load('department'), ...$employee->reportingChain()])
                ->map(fn (Employee $chainMember): array => [
                    'id' => $chainMember->id,
                    'name' => $chainMember->name,
                    'department' => $chainMember->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
                ])
                ->all(),
            'canViewEmployeePage' => $actor->can('view', $employee),
        ]);
    }
}
