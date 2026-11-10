<?php

use App\Enums\Permission;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeInvitationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoleHolderController;
use App\Http\Controllers\TopEmployeeController;
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
    Route::post('employees/{employee}/top', [TopEmployeeController::class, 'store'])->middleware('can:becomeTop,employee')->name('employees.top.store');

    Route::middleware('can:'.Permission::ViewDirectory->value)->group(function () {
        Route::get('directory', [DirectoryController::class, 'index'])->name('directory.index');
        Route::get('directory/{employee}', [DirectoryController::class, 'show'])->name('directory.show');
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
