<?php

use App\Enums\Permission;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TravelExpense;
use App\Models\WorkEntry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

covers(WorkEntry::class, TravelExpense::class, Role::class);

it('makes Locked Work Entries and Travel Expenses Approved, keeping who approved them and when, and removes the lock grants', function () {
    $ben = Employee::factory()->create();
    $finn = Employee::factory()->create();
    $workEntry = WorkEntry::factory()->approved($ben)->create(['approved_at' => '2026-11-10 10:00']);
    $pendingEntry = WorkEntry::factory()->create();
    $travelExpense = TravelExpense::factory()->approved()->create(['approved_by' => null, 'approved_at' => '2026-11-11 10:00']);
    $payroll = Role::factory()->withPermissions([Permission::ReopenWorkEntries->value => Scope::Everyone])->create();
    $migration = ['--path' => 'database/migrations/2026_11_18_163202_remove_locked_from_work_entries_and_travel_expenses.php'];

    Artisan::call('migrate:rollback', $migration);
    DB::table('work_entries')->where('id', $workEntry->id)->update(['state' => 'locked', 'locked_by' => $finn->id, 'locked_at' => '2026-11-12 10:00']);
    DB::table('travel_expenses')->where('id', $travelExpense->id)->update(['state' => 'locked', 'locked_by' => $finn->id, 'locked_at' => '2026-11-12 10:00']);
    DB::table('role_permissions')->insert([
        ['role_id' => $payroll->id, 'permission' => 'lock-work-entries', 'scope' => null],
        ['role_id' => $payroll->id, 'permission' => 'lock-travel-expenses', 'scope' => null],
    ]);
    Artisan::call('migrate', $migration);

    expect(DB::table('work_entries')->where('id', $workEntry->id)->first(['state', 'approved_by', 'approved_at']))
        ->toEqual((object) ['state' => 'approved', 'approved_by' => $ben->id, 'approved_at' => '2026-11-10 10:00:00'])
        ->and(DB::table('work_entries')->where('id', $pendingEntry->id)->value('state'))->toBe('pending')
        ->and(DB::table('travel_expenses')->where('id', $travelExpense->id)->first(['state', 'approved_by', 'approved_at']))
        ->toEqual((object) ['state' => 'approved', 'approved_by' => null, 'approved_at' => '2026-11-11 10:00:00'])
        ->and(DB::table('role_permissions')->pluck('permission')->all())->toBe(['reopen-work-entries']);
});
