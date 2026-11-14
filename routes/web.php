<?php

use App\Enums\Permission;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeInvitationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LockController;
use App\Http\Controllers\PaidDaysController;
use App\Http\Controllers\PayEntryController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReopeningController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoleHolderController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TimeOffApprovalController;
use App\Http\Controllers\TimeOffCancellationController;
use App\Http\Controllers\TimeOffController;
use App\Http\Controllers\TimeOffDeclineController;
use App\Http\Controllers\TimeOffPreviewController;
use App\Http\Controllers\TopEmployeeController;
use App\Http\Controllers\WorkEntryController;
use App\Http\Controllers\WorkEntryCopyController;
use App\Http\Controllers\WorkEntryDeclineController;
use App\Http\Controllers\WorkEntryListController;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): RedirectResponse => auth()->check() ? to_route('landing') : to_route('login'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation', [InvitationController::class, 'store'])->name('invitation.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('home', HomeController::class)->name('landing');

    Route::middleware('can:create,'.Employee::class)->group(function () {
        Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    });

    Route::middleware('can:update,employee')->group(function () {
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::patch('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    });

    Route::get('employees', [EmployeeController::class, 'index'])->middleware('can:viewAny,'.Employee::class)->name('employees.index');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->middleware('can:view,employee')->name('employees.show');
    Route::post('employees/{employee}/invitation', [EmployeeInvitationController::class, 'store'])->middleware('can:resendInvitation,employee')->name('employees.invitation.store');
    Route::post('employees/{employee}/paid-days', [PaidDaysController::class, 'store'])->middleware('can:setPaidDays,employee')->name('employees.paid-days.store');
    Route::post('employees/{employee}/top', [TopEmployeeController::class, 'store'])->middleware('can:becomeTop,employee')->name('employees.top.store');

    Route::post('employees/{employee}/assignments', [AssignmentController::class, 'store'])->middleware('can:create,'.Assignment::class.',employee')->name('employees.assignments.store');
    Route::patch('assignments/{assignment}', [AssignmentController::class, 'update'])->middleware('can:update,assignment')->name('assignments.update');

    Route::middleware('can:managePayOn,assignment')->group(function () {
        Route::post('assignments/{assignment}/pay', [PayEntryController::class, 'store'])->name('assignments.pay.store');
        Route::delete('assignments/{assignment}/pay/{payEntry}', [PayEntryController::class, 'destroy'])->scopeBindings()->name('assignments.pay.destroy');
    });

    Route::get('team', TeamController::class)->middleware('can:viewTeam,'.Employee::class)->name('team.index');

    Route::get('work-entries', [WorkEntryController::class, 'index'])->name('work-entries.index');
    Route::post('employees/{employee}/work-entries', [WorkEntryController::class, 'store'])->middleware('can:create,'.WorkEntry::class.',employee')->name('employees.work-entries.store');
    Route::post('employees/{employee}/work-entry-copies', [WorkEntryCopyController::class, 'store'])->middleware('can:create,'.WorkEntry::class.',employee')->name('employees.work-entry-copies.store');
    Route::middleware('can:viewApprovals,'.WorkEntry::class)->group(function () {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals', [ApprovalController::class, 'store'])->name('approvals.store');
        Route::post('approvals/declines', [WorkEntryDeclineController::class, 'store'])->name('approvals.declines.store');
    });
    Route::get('work-entries/all', [WorkEntryListController::class, 'index'])->middleware('can:viewList,'.WorkEntry::class)->name('work-entry-list.index');
    Route::post('reopenings', [ReopeningController::class, 'store'])->middleware('can:reopen,'.WorkEntry::class)->name('reopenings.store');
    Route::middleware('can:lock,'.WorkEntry::class)->group(function () {
        Route::get('locks/create', [LockController::class, 'create'])->name('locks.create');
        Route::post('locks', [LockController::class, 'store'])->name('locks.store');
    });
    Route::patch('work-entries/{workEntry}', [WorkEntryController::class, 'update'])->middleware('can:update,workEntry')->name('work-entries.update');
    Route::delete('work-entries/{workEntry}', [WorkEntryController::class, 'destroy'])->middleware('can:delete,workEntry')->name('work-entries.destroy');

    Route::get('time-off', [TimeOffController::class, 'index'])->name('time-off.index');
    Route::get('time-off/preview', [TimeOffPreviewController::class, 'show'])->name('time-off.preview');
    Route::post('time-off', [TimeOffController::class, 'store'])->name('time-off.store');
    Route::post('time-off/{timeOff}/cancellation', [TimeOffCancellationController::class, 'store'])->middleware('can:cancel,timeOff')->name('time-off.cancellation.store');
    Route::middleware('can:decide,timeOff')->group(function () {
        Route::post('time-off/{timeOff}/approval', [TimeOffApprovalController::class, 'store'])->name('time-off.approval.store');
        Route::post('time-off/{timeOff}/decline', [TimeOffDeclineController::class, 'store'])->name('time-off.decline.store');
    });

    Route::middleware('can:'.Permission::ViewDirectory->value)->group(function () {
        Route::get('directory', [DirectoryController::class, 'index'])->name('directory.index');
        Route::get('directory/{employee}', [DirectoryController::class, 'show'])->name('directory.show');
    });

    Route::middleware('can:'.Permission::ManageClients->value)->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::patch('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    });

    Route::get('projects', [ProjectController::class, 'index'])->middleware('can:viewAny,'.Project::class)->name('projects.index');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('can:view,project')->name('projects.show');
    Route::post('projects', [ProjectController::class, 'store'])->middleware('can:create,'.Project::class)->name('projects.store');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware('can:update,project')->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('can:delete,project')->name('projects.destroy');

    Route::middleware('can:'.Permission::ManageCompany->value)->group(function () {
        Route::get('company', [CompanySettingsController::class, 'edit'])->name('company.edit');
        Route::patch('company', [CompanySettingsController::class, 'update'])->name('company.update');
    });

    Route::middleware('can:'.Permission::ManageDepartments->value)->group(function () {
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    });

    Route::middleware('can:'.Permission::ManageRoles->value)->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('roles/{role}/holders', [RoleHolderController::class, 'store'])->name('roles.holders.store');
        Route::delete('roles/{role}/holders/{employee}', [RoleHolderController::class, 'destroy'])->name('roles.holders.destroy');
    });
});

require __DIR__.'/settings.php';
