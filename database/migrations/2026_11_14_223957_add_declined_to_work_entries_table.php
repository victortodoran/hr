<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Work Entry can also be Declined, which records who declined it, when
     * and why, and nothing about approving or locking. In every other state
     * those stay empty.
     */
    public function up(): void
    {
        Schema::table('work_entries', function (Blueprint $table) {
            $table->foreignId('declined_by')->nullable()->after('locked_at')->constrained('employees')->restrictOnDelete();
            $table->timestamp('declined_at')->nullable()->after('declined_by');
            $table->text('decline_reason')->nullable()->after('declined_at');
        });

        DB::statement('ALTER TABLE work_entries DROP CONSTRAINT work_entries_state');
        DB::statement(<<<'SQL'
            ALTER TABLE work_entries ADD CONSTRAINT work_entries_state CHECK (
                (state = 'pending' AND approved_by IS NULL AND approved_at IS NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'approved' AND approved_by IS NOT NULL AND approved_at IS NOT NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'locked' AND approved_by IS NOT NULL AND approved_at IS NOT NULL AND locked_by IS NOT NULL AND locked_at IS NOT NULL)
                OR (state = 'declined' AND approved_by IS NULL AND approved_at IS NULL AND locked_by IS NULL AND locked_at IS NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE work_entries ADD CONSTRAINT work_entries_decline CHECK (
                (state = 'declined' AND declined_by IS NOT NULL AND declined_at IS NOT NULL AND decline_reason IS NOT NULL)
                OR (state <> 'declined' AND declined_by IS NULL AND declined_at IS NULL AND decline_reason IS NULL)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE work_entries DROP CONSTRAINT work_entries_decline');
        DB::statement('ALTER TABLE work_entries DROP CONSTRAINT work_entries_state');
        DB::statement(<<<'SQL'
            ALTER TABLE work_entries ADD CONSTRAINT work_entries_state CHECK (
                (state = 'pending' AND approved_by IS NULL AND approved_at IS NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'approved' AND approved_by IS NOT NULL AND approved_at IS NOT NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'locked' AND approved_by IS NOT NULL AND approved_at IS NOT NULL AND locked_by IS NOT NULL AND locked_at IS NOT NULL)
            )
            SQL);

        Schema::table('work_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('declined_by');
            $table->dropColumn(['declined_at', 'decline_reason']);
        });
    }
};
