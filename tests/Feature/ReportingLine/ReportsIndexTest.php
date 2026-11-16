<?php

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * Finding someone's Reports walks down the reporting line by Manager at
 * every level, so that walk must be able to use the index on manager_id.
 */
it('lets the Reports query find each level by Manager through its index', function () {
    $ada = Employee::factory()->top()->create();
    Employee::factory()->reportingTo($ada)->create();

    $plan = DB::transaction(function () use ($ada): string {
        DB::statement('SET LOCAL enable_seqscan = off');

        return collect(DB::select('EXPLAIN '.Employee::query()->reportsOf($ada)->toRawSql()))->pluck('QUERY PLAN')->implode("\n");
    });

    expect($plan)->toContain('employees_manager_id_index');
});
