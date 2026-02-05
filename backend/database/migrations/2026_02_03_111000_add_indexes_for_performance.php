<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add composite indexes for common queries
        // Note: Using try-catch to handle existing indexes gracefully
        
        // Projects - status and project_manager_id
        try {
            Schema::table('projects', function (Blueprint $table) {
                $table->index(['status', 'project_manager_id'], 'projects_status_pm_index');
                $table->index(['status', 'team_id'], 'projects_status_team_index');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        // Attendance - user_id and date
        try {
            Schema::table('attendance', function (Blueprint $table) {
                $table->index(['user_id', 'date', 'status'], 'attendance_user_date_index');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        // Time Entries - user_id, date, and status
        try {
            Schema::table('time_entries', function (Blueprint $table) {
                $table->index(['user_id', 'date', 'status'], 'time_entries_user_date_status_index');
                $table->index(['project_id', 'status'], 'time_entries_project_status_index');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        // EOD Reports - user_id, date, and status
        try {
            Schema::table('eod_reports', function (Blueprint $table) {
                $table->index(['user_id', 'date', 'status'], 'eod_reports_user_date_status_index');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        // Daily Plans - user_id and date
        try {
            Schema::table('daily_plans', function (Blueprint $table) {
                $table->index(['user_id', 'date', 'status'], 'daily_plans_user_date_status_index');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        // Approvals - approver_id and status
        try {
            Schema::table('approvals', function (Blueprint $table) {
                $table->index(['approver_id', 'status'], 'approvals_approver_status_index');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }

        // Notifications - user_id and is_read
        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_read_index');
            });
        } catch (\Exception $e) {
            // Index might already exist, continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropIndex('projects_status_pm_index');
                $table->dropIndex('projects_status_team_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('attendance', function (Blueprint $table) {
                $table->dropIndex('attendance_user_date_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('time_entries', function (Blueprint $table) {
                $table->dropIndex('time_entries_user_date_status_index');
                $table->dropIndex('time_entries_project_status_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('eod_reports', function (Blueprint $table) {
                $table->dropIndex('eod_reports_user_date_status_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('daily_plans', function (Blueprint $table) {
                $table->dropIndex('daily_plans_user_date_status_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('approvals', function (Blueprint $table) {
                $table->dropIndex('approvals_approver_status_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_user_read_index');
            });
        } catch (\Exception $e) {}
    }
};
