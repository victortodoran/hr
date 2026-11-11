<?php

use App\Models\Assignment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

covers(Assignment::class);

it('sets every Internal Assignment left empty to 0% capacity and keeps every value already entered', function () {
    $unplanned = Assignment::factory()->internal()->create();
    $planned = Assignment::factory()->internal()->create(['capacity' => 30]);
    $relaunch = Assignment::factory()->create(['capacity' => 70]);
    $migration = ['--path' => 'database/migrations/2026_11_11_145014_rename_assignment_share_to_capacity.php'];

    Artisan::call('migrate:rollback', $migration);
    DB::table('assignments')->where('id', $unplanned->id)->update(['share' => null]);
    Artisan::call('migrate', $migration);

    expect(DB::table('assignments')->orderBy('id')->pluck('capacity', 'id')->all())
        ->toBe([$unplanned->id => 0, $planned->id => 30, $relaunch->id => 70]);
});
