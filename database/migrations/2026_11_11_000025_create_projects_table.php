<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create Projects, and the Internal Project for a company installed
     * before Projects existed.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX projects_single_internal ON projects (is_internal) WHERE is_internal');
        DB::statement(<<<'SQL'
            ALTER TABLE projects ADD CONSTRAINT projects_dates_and_client CHECK (
                CASE WHEN is_internal
                    THEN start_date IS NULL AND end_date IS NULL AND client_id IS NULL
                    ELSE start_date IS NOT NULL AND (end_date IS NULL OR end_date >= start_date)
                END
            )
            SQL);

        $company = DB::table('company_settings')->value('name');

        if ($company !== null) {
            DB::table('projects')->insert(['name' => $company, 'is_internal' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
