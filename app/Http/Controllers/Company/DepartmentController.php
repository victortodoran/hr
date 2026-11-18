<?php

namespace App\Http\Controllers\Company;

use App\Actions\DeleteDepartment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\DepartmentRequest;
use App\Models\Department;
use App\Pages\Company\DepartmentListPage;
use Illuminate\Http\RedirectResponse;
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
        return Inertia::render('departments/Index', DepartmentListPage::props());
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
    public function destroy(Department $department, DeleteDepartment $deleteDepartment): RedirectResponse
    {
        $deleteDepartment->delete($department);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department deleted.')]);

        return to_route('departments.index');
    }
}
