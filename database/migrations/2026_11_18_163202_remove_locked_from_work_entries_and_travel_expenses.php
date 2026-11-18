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
     * Work Entries and Travel Expenses are no longer Locked: Approved is
     * their last state (ADR 0005). Locked ones become Approved and keep who
     * approved them and when, who locked them and when goes, and so do the
     * grants Roles still store for the two lock Permissions.
     */
    public function up(): void
    {
        foreach (['work_entries', 'travel_expenses'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_state");
            DB::table($table)->where('state', 'locked')->update(['state' => 'approved']);

            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('locked_by');
                $table->dropColumn('locked_at');
            });
        }

        DB::statement(<<<'SQL'
            ALTER TABLE work_entries ADD CONSTRAINT work_entries_state CHECK (
                (state = 'pending' AND approved_by IS NULL AND approved_at IS NULL)
                OR (state = 'approved' AND approved_by IS NOT NULL AND approved_at IS NOT NULL)
                OR (state = 'declined' AND approved_by IS NULL AND approved_at IS NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE travel_expenses ADD CONSTRAINT travel_expenses_state CHECK (
                (state = 'pending' AND approved_by IS NULL AND approved_at IS NULL)
                OR (state = 'approved' AND approved_at IS NOT NULL)
                OR (state = 'declined' AND approved_by IS NULL AND approved_at IS NULL)
            )
            SQL);

        DB::table('role_permissions')->whereIn('permission', ['lock-work-entries', 'lock-travel-expenses'])->delete();
    }

    /**
     * Reverse the migrations.
     *
     * The columns and the Locked state come back, but which Work Entries
     * and Travel Expenses were Locked, by whom and when, and the removed
     * grants, are not restored.
     */
    public function down(): void
    {
        Schema::table('work_entries', function (Blueprint $table) {
            $table->foreignId('locked_by')->nullable()->after('approved_at')->constrained('employees')->restrictOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
        });
        Schema::table('travel_expenses', function (Blueprint $table) {
            $table->foreignId('locked_by')->nullable()->after('approved_at')->constrained('employees')->restrictOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by');
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
        DB::statement('ALTER TABLE travel_expenses DROP CONSTRAINT travel_expenses_state');
        DB::statement(<<<'SQL'
            ALTER TABLE travel_expenses ADD CONSTRAINT travel_expenses_state CHECK (
                (state = 'pending' AND approved_by IS NULL AND approved_at IS NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'approved' AND approved_at IS NOT NULL AND locked_by IS NULL AND locked_at IS NULL)
                OR (state = 'locked' AND approved_at IS NOT NULL AND locked_by IS NOT NULL AND locked_at IS NOT NULL)
                OR (state = 'declined' AND approved_by IS NULL AND approved_at IS NULL AND locked_by IS NULL AND locked_at IS NULL)
            )
            SQL);
    }
};
