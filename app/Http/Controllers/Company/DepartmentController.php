<?php

namespace App\Http\Controllers\Company;

use App\Actions\Company\DeleteDepartment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\DepartmentRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Pages\Company\DepartmentListPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(#[CurrentUser] Employee $actor): Response
    {
        return Inertia::render('departments/Index', DepartmentListPage::props($actor));
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department added.')]);

        return to_route('departments.index');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department renamed.')]);

        return to_route('departments.index');
    }

    public function destroy(Department $department, DeleteDepartment $deleteDepartment): RedirectResponse
    {
        $deleteDepartment->delete($department);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department deleted.')]);

        return to_route('departments.index');
    }
}
