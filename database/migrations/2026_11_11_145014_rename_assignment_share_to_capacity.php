<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every Assignment has a Capacity: a whole percentage from 0% to 100%, always
 * set by hand. Only an Internal Assignment could leave the old share empty, and
 * those start at 0% now. Rolling back can't tell those 0s from ones entered on
 * purpose, so it keeps them and lets the old share be 0 too.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('assignments')->whereNull('share')->update(['share' => 0]);

        DB::statement('ALTER TABLE assignments DROP CONSTRAINT assignments_share');

        Schema::table('assignments', function (Blueprint $table) {
            $table->renameColumn('share', 'capacity');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE assignments ADD CONSTRAINT assignments_capacity CHECK (capacity BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assignments DROP CONSTRAINT assignments_capacity');

        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->nullable()->change();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->renameColumn('capacity', 'share');
        });

        DB::statement('ALTER TABLE assignments ADD CONSTRAINT assignments_share CHECK (share IS NULL OR share BETWEEN 0 AND 100)');
    }
};
