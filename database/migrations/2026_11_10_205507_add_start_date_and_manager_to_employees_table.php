<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every Employee has a Start Date. Every Employee except the top one has
     * a Manager, which the app checks since the top Employee has none.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('department_id');
            $table->foreignId('manager_id')->nullable()->after('start_date')->constrained('employees')->restrictOnDelete();
        });

        DB::table('employees')->update(['start_date' => DB::raw('CAST(created_at AS DATE)')]);

        Schema::table('employees', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn('start_date');
        });
    }
};
