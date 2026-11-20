<?php

use App\Enums\Permission;
use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Access\RoleHolderController;
use App\Http\Controllers\Assignments\AssignmentController;
use App\Http\Controllers\Assignments\PayEntryController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Company\CompanySettingsController;
use App\Http\Controllers\Company\DepartmentController;
use App\Http\Controllers\Employees\DirectoryController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\EmployeeInvitationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Performance\GoalCommentController;
use App\Http\Controllers\Performance\GoalController;
use App\Http\Controllers\Performance\GoalOutcomeController;
use App\Http\Controllers\Performance\GoalProgressController;
use App\Http\Controllers\Performance\MyPerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationCompletionController;
use App\Http\Controllers\Performance\PerformanceEvaluationController;
use App\Http\Controllers\Performance\PerformanceEvaluationReopeningController;
use App\Http\Controllers\Performance\PerformanceEvaluationSummaryController;
use App\Http\Controllers\Projects\ClientController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Search\CommandMenuController;
use App\Http\Controllers\TimeOff\PaidDaysController;
use App\Http\Controllers\TimeOff\TimeOffApprovalController;
use App\Http\Controllers\TimeOff\TimeOffCancellationController;
use App\Http\Controllers\TimeOff\TimeOffController;
use App\Http\Controllers\TimeOff\TimeOffDeclineController;
use App\Http\Controllers\TimeOff\TimeOffListController;
use App\Http\Controllers\TimeOff\TimeOffPreviewController;
use App\Http\Controllers\Travel\TravelExpenseApprovalController;
use App\Http\Controllers\Travel\TravelExpenseController;
use App\Http\Controllers\Travel\TravelExpenseDeclineController;
use App\Http\Controllers\Travel\TravelExpenseExportController;
use App\Http\Controllers\Travel\TravelExpenseReceiptController;
use App\Http\Controllers\Travel\TravelExpenseReopeningController;
use App\Http\Controllers\Travel\TravelRequestApprovalController;
use App\Http\Controllers\Travel\TravelRequestCancellationController;
use App\Http\Controllers\Travel\TravelRequestController;
use App\Http\Controllers\Travel\TravelRequestDeclineController;
use App\Http\Controllers\Travel\TravelRequestListController;
use App\Http\Controllers\WorkEntries\ApprovalController;
use App\Http\Controllers\WorkEntries\ReopeningController;
use App\Http\Controllers\WorkEntries\WorkEntryController;
use App\Http\Controllers\WorkEntries\WorkEntryCopyController;
use App\Http\Controllers\WorkEntries\WorkEntryDeclineController;
use App\Http\Controllers\WorkEntries\WorkEntryExportController;
use App\Http\Controllers\WorkEntries\WorkEntryListController;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\PerformanceEvaluation;
use App\Models\Project;
use App\Models\TimeOff;
use App\Models\TravelExpense;
use App\Models\TravelRequest;
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
    Route::get('search', CommandMenuController::class)->name('search');

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

    Route::post('employees/{employee}/assignments', [AssignmentController::class, 'store'])->middleware('can:create,'.Assignment::class.',employee')->name('employees.assignments.store');
    Route::patch('assignments/{assignment}', [AssignmentController::class, 'update'])->middleware('can:update,assignment')->name('assignments.update');
    Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy'])->middleware('can:delete,assignment')->name('assignments.destroy');

    Route::middleware('can:managePayOn,assignment')->group(function () {
        Route::post('assignments/{assignment}/pay', [PayEntryController::class, 'store'])->name('assignments.pay.store');
        Route::delete('assignments/{assignment}/pay/{payEntry}', [PayEntryController::class, 'destroy'])->scopeBindings()->name('assignments.pay.destroy');
    });

    Route::redirect('team', '/directory?tab=reports')->name('team.index');
    Route::get('directory', [DirectoryController::class, 'index'])->middleware('can:viewDirectory,'.Employee::class)->name('directory.index');

    Route::get('work-entries', [WorkEntryController::class, 'index'])->name('work-entries.index');
    Route::post('employees/{employee}/work-entries', [WorkEntryController::class, 'store'])->middleware('can:create,'.WorkEntry::class.',employee')->name('employees.work-entries.store');
    Route::post('employees/{employee}/work-entry-copies', [WorkEntryCopyController::class, 'store'])->middleware('can:create,'.WorkEntry::class.',employee')->name('employees.work-entry-copies.store');
    Route::middleware('can:viewApprovals,'.WorkEntry::class)->group(function () {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals', [ApprovalController::class, 'store'])->name('approvals.store');
        Route::post('approvals/declines', [WorkEntryDeclineController::class, 'store'])->name('approvals.declines.store');
        Route::post('approvals/travel-expenses', [TravelExpenseApprovalController::class, 'store'])->name('approvals.travel-expenses.store');
        Route::post('approvals/travel-expenses/declines', [TravelExpenseDeclineController::class, 'store'])->name('approvals.travel-expenses.declines.store');
    });
    Route::get('work-entries/all', [WorkEntryListController::class, 'index'])->middleware('can:viewList,'.WorkEntry::class)->name('work-entry-list.index');
    Route::get('work-entries/export', [WorkEntryExportController::class, 'show'])->name('work-entry-exports.show');
    Route::post('reopenings', [ReopeningController::class, 'store'])->middleware('can:reopen,'.WorkEntry::class)->name('reopenings.store');
    Route::patch('work-entries/{workEntry}', [WorkEntryController::class, 'update'])->middleware('can:update,workEntry')->name('work-entries.update');
    Route::delete('work-entries/{workEntry}', [WorkEntryController::class, 'destroy'])->middleware('can:delete,workEntry')->name('work-entries.destroy');

    Route::get('time-off', [TimeOffController::class, 'index'])->name('time-off.index');
    Route::get('time-off/all', [TimeOffListController::class, 'index'])->middleware('can:viewList,'.TimeOff::class)->name('time-off-list.index');
    Route::get('time-off/create', [TimeOffController::class, 'create'])->name('time-off.create');
    Route::get('time-off/preview', [TimeOffPreviewController::class, 'show'])->name('time-off.preview');
    Route::post('time-off', [TimeOffController::class, 'store'])->name('time-off.store');
    Route::post('time-off/{timeOff}/cancellation', [TimeOffCancellationController::class, 'store'])->middleware('can:cancel,timeOff')->name('time-off.cancellation.store');
    Route::middleware('can:decide,timeOff')->group(function () {
        Route::post('time-off/{timeOff}/approval', [TimeOffApprovalController::class, 'store'])->name('time-off.approval.store');
        Route::post('time-off/{timeOff}/decline', [TimeOffDeclineController::class, 'store'])->name('time-off.decline.store');
    });

    Route::get('travel-requests', [TravelRequestController::class, 'index'])->name('travel-requests.index');
    Route::get('travel-requests/all', [TravelRequestListController::class, 'index'])->middleware('can:viewList,'.TravelRequest::class)->name('travel-request-list.index');
    Route::get('travel-requests/create', [TravelRequestController::class, 'create'])->name('travel-requests.create');
    Route::post('travel-requests', [TravelRequestController::class, 'store'])->name('travel-requests.store');
    Route::get('travel-requests/{travelRequest}', [TravelRequestController::class, 'show'])->middleware('can:view,travelRequest')->name('travel-requests.show');
    Route::post('travel-requests/{travelRequest}/cancellation', [TravelRequestCancellationController::class, 'store'])->middleware('can:cancel,travelRequest')->name('travel-requests.cancellation.store');
    Route::get('travel-requests/{travelRequest}/expenses/create', [TravelExpenseController::class, 'create'])->middleware('can:create,'.TravelExpense::class.',travelRequest')->name('travel-requests.expenses.create');
    Route::post('travel-requests/{travelRequest}/expenses', [TravelExpenseController::class, 'store'])->middleware('can:create,'.TravelExpense::class.',travelRequest')->name('travel-requests.expenses.store');
    Route::post('travel-requests/{travelRequest}/expenses/reopenings', [TravelExpenseReopeningController::class, 'store'])->middleware('can:reopen,'.TravelExpense::class)->name('travel-requests.expenses.reopenings.store');
    Route::get('travel-requests/{travelRequest}/expenses/{travelExpense}/edit', [TravelExpenseController::class, 'edit'])->middleware('can:update,travelExpense')->scopeBindings()->name('travel-requests.expenses.edit');
    Route::put('travel-expenses/{travelExpense}', [TravelExpenseController::class, 'update'])->middleware('can:update,travelExpense')->name('travel-expenses.update');
    Route::delete('travel-expenses/{travelExpense}', [TravelExpenseController::class, 'destroy'])->middleware('can:delete,travelExpense')->name('travel-expenses.destroy');
    Route::get('travel-expenses/export', [TravelExpenseExportController::class, 'show'])->middleware('can:viewList,'.TravelRequest::class)->name('travel-expense-exports.show');
    Route::get('travel-expenses/{travelExpense}/receipt', [TravelExpenseReceiptController::class, 'show'])->middleware('can:view,travelExpense')->name('travel-expenses.receipt.show');
    Route::middleware('can:decide,travelRequest')->group(function () {
        Route::post('travel-requests/{travelRequest}/approval', [TravelRequestApprovalController::class, 'store'])->name('travel-requests.approval.store');
        Route::post('travel-requests/{travelRequest}/decline', [TravelRequestDeclineController::class, 'store'])->name('travel-requests.decline.store');
    });

    Route::get('my-performance-evaluations', MyPerformanceEvaluationController::class)->name('my-performance-evaluations.index');
    Route::get('performance-evaluations', [PerformanceEvaluationController::class, 'index'])->middleware('can:viewList,'.PerformanceEvaluation::class)->name('performance-evaluations.index');
    Route::get('performance-evaluations/create', [PerformanceEvaluationController::class, 'create'])->middleware('can:createAny,'.PerformanceEvaluation::class)->name('performance-evaluations.create');
    Route::post('performance-evaluations', [PerformanceEvaluationController::class, 'store'])->name('performance-evaluations.store');
    Route::get('performance-evaluations/{performanceEvaluation}', [PerformanceEvaluationController::class, 'show'])->middleware('can:view,performanceEvaluation')->name('performance-evaluations.show');
    Route::get('performance-evaluations/{performanceEvaluation}/goals/{goal}', [GoalController::class, 'show'])->middleware('can:view,performanceEvaluation')->scopeBindings()->whereNumber('goal')->name('performance-evaluations.goals.show');
    Route::put('performance-evaluations/{performanceEvaluation}/goals/{goal}/progress', [GoalProgressController::class, 'update'])->middleware('can:changeProgress,performanceEvaluation')->scopeBindings()->name('performance-evaluations.goals.progress.update');
    Route::post('performance-evaluations/{performanceEvaluation}/goals/{goal}/comments', [GoalCommentController::class, 'store'])->middleware('can:comment,performanceEvaluation')->scopeBindings()->name('performance-evaluations.goals.comments.store');
    Route::post('performance-evaluations/{performanceEvaluation}/reopening', [PerformanceEvaluationReopeningController::class, 'store'])->middleware('can:reopen,performanceEvaluation')->name('performance-evaluations.reopening.store');
    Route::middleware('can:edit,performanceEvaluation')->scopeBindings()->group(function () {
        Route::get('performance-evaluations/{performanceEvaluation}/goals/create', [GoalController::class, 'create'])->name('performance-evaluations.goals.create');
        Route::post('performance-evaluations/{performanceEvaluation}/goals', [GoalController::class, 'store'])->name('performance-evaluations.goals.store');
        Route::put('performance-evaluations/{performanceEvaluation}/goals/{goal}', [GoalController::class, 'update'])->name('performance-evaluations.goals.update');
        Route::delete('performance-evaluations/{performanceEvaluation}/goals/{goal}', [GoalController::class, 'destroy'])->name('performance-evaluations.goals.destroy');
        Route::put('performance-evaluations/{performanceEvaluation}/goals/{goal}/outcome', [GoalOutcomeController::class, 'update'])->name('performance-evaluations.goals.outcome.update');
        Route::put('performance-evaluations/{performanceEvaluation}/summary', [PerformanceEvaluationSummaryController::class, 'update'])->name('performance-evaluations.summary.update');
        Route::post('performance-evaluations/{performanceEvaluation}/completion', [PerformanceEvaluationCompletionController::class, 'store'])->name('performance-evaluations.completion.store');
    });

    Route::middleware('can:'.Permission::ViewDirectory->value)->group(function () {
        Route::get('directory/{employee}', [DirectoryController::class, 'show'])->whereNumber('employee')->name('directory.show');
    });

    Route::middleware('can:'.Permission::ManageClients->value)->group(function () {
        Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::patch('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    });

    Route::get('projects', [ProjectController::class, 'index'])->middleware('can:viewAny,'.Project::class)->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->middleware('can:create,'.Project::class)->name('projects.create');
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
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('roles/{role}/holders', [RoleHolderController::class, 'store'])->name('roles.holders.store');
        Route::delete('roles/{role}/holders/{employee}', [RoleHolderController::class, 'destroy'])->name('roles.holders.destroy');
    });
});

require __DIR__.'/settings.php';
