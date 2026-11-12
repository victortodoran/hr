<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every Work Entry is Pending, Approved or Locked. An Approved one
     * records who approved it and when; a Locked one also records who locked
     * it and when. Existing Work Entries start Pending, since nobody has
     * approved them yet.
     */
    public function up(): void
    {
        Schema::table('work_entries', function (Blueprint $table) {
            $table->string('state')->default('pending')->after('description');
            $table->foreignId('approved_by')->nullable()->after('state')->constrained('employees')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('locked_by')->nullable()->after('approved_at')->constrained('employees')->restrictOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE work_entries ADD CONSTRAINT work_entries_state CHECK (
                (state = 'pending' AND approved_by IS NULL AND approved_at IS NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'approved' AND approved_by IS NOT NULL AND approved_at IS NOT NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'locked' AND approved_by IS NOT NULL AND approved_at IS NOT NULL AND locked_by IS NOT NULL AND locked_at IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE work_entries DROP CONSTRAINT work_entries_state');

        Schema::table('work_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'state']);
        });
    }
};
