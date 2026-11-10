<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The last day an Employee works; from the next day they are a Former
     * Employee.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('leaving_date')->nullable()->after('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('leaving_date');
        });
    }
};
