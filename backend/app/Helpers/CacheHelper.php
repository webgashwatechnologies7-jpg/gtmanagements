<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

/**
 * Dashboard cache TTL: 600 seconds (AnalyticsController).
 * Call clearDashboardCache() when data that feeds the dashboard changes so the user sees fresh stats.
 *
 * When to call clearDashboardCache($userId) or clearDashboardCache($userId, $role):
 * - Project created/updated/deleted or assignment changed → clear for admin, PM, TL, or affected user.
 * - Team membership or team lead/PM changed → clear for that user and admins.
 * - EOD/approval status changed → clear for report owner and approvers.
 * - Attendance/leave change that affects dashboard counts → clear for that user.
 */
class CacheHelper
{
    /**
     * Clear dashboard cache for a user (optionally for a specific role key).
     */
    public static function clearDashboardCache($userId, $role = null)
    {
        $keys = [];
        
        if ($role === 'admin' || !$role) {
            $keys[] = 'dashboard_admin_' . $userId;
        }
        if ($role === 'project_manager' || !$role) {
            $keys[] = 'dashboard_pm_' . $userId;
        }
        if ($role === 'team_lead' || !$role) {
            $keys[] = 'dashboard_tl_' . $userId;
        }
        if ($role === 'employee' || !$role) {
            $keys[] = 'dashboard_employee_' . $userId;
        }

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clear report cache
     */
    public static function clearReportCache($type, $entityId, $period = null)
    {
        $pattern = "report_{$type}_{$entityId}";
        if ($period) {
            $pattern .= "_{$period}";
        }

        // Note: This is a simplified version. In production, use Redis tags or store keys
        // For now, we'll clear specific known patterns
        Cache::flush(); // In production, use more targeted clearing
    }

    /**
     * Invalidate task statistics cache (call when tasks are created/updated/deleted).
     * Uses version key so next statistics request gets fresh data.
     */
    public static function invalidateTaskStatistics(): void
    {
        Cache::increment('task_stats_version');
    }

    /**
     * Clear all caches (use with caution)
     */
    public static function clearAllCaches()
    {
        Cache::flush();
    }
}
