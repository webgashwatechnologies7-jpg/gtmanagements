<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 14: Performance - composite indexes for task list filters.
     */
    public function up(): void
    {
        try {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['project_id', 'status'], 'tasks_project_status_index');
                $table->index(['assigned_to_user_id', 'status'], 'tasks_assigned_status_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex('tasks_project_status_index');
                $table->dropIndex('tasks_assigned_status_index');
            });
        } catch (\Exception $e) {
            //
        }
    }
};
