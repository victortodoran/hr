<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets those who manage Departments keep the company's flat list of
 * Departments.
 */
class DepartmentController extends Controller
{
    /**
     * Show every Department, by name.
     */
    public function index(): Response
    {
        return Inertia::render('departments/Index', [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department created.')]);

        return to_route('departments.index');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department renamed.')]);

        return to_route('departments.index');
    }

    /**
     * Delete a Department, refusing while any Employee still belongs to it.
     */
    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            throw ValidationException::withMessages([
                'department' => __(':department still has Employees. Move them to another Department before deleting it.', [
                    'department' => $department->name,
                ]),
            ]);
        }

        $department->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department deleted.')]);

        return to_route('departments.index');
    }
}
