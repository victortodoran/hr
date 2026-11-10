<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Emails are now stored in lowercase; bring existing ones in line so
     * Employees added with capitals can sign in.
     */
    public function up(): void
    {
        DB::table('employees')->update(['email' => DB::raw('LOWER(email)')]);
    }

    /**
     * Reverse the migrations.
     *
     * The original capitals are not kept, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
