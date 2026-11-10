<?php

use App\Enums\Permission;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeInvitationController;
use App\Models\Employee;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation', [InvitationController::class, 'store'])->name('invitation.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::middleware('can:create,'.Employee::class)->group(function () {
        Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    });

    Route::middleware('can:update,employee')->group(function () {
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::patch('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    });

    Route::post('employees/{employee}/invitation', [EmployeeInvitationController::class, 'store'])->middleware('can:resendInvitation,employee')->name('employees.invitation.store');

    Route::middleware('can:'.Permission::ManageDepartments->value)->group(function () {
        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    });
});

require __DIR__.'/settings.php';
