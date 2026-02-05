<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rate limiting (Phase 15 - Security)
|--------------------------------------------------------------------------
| register: 3/min, login: 5/min, protected API: 60/min.
| SanitizeInput middleware runs on all API requests (strip HTML).
*/

// Public routes with rate limiting
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:3,1');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// Protected routes with rate limiting
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Auth routes (no role required)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // User management – middleware: admin or HR (controller restricts create/update/delete to admin only)
    Route::middleware(['role:admin,hr'])->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/users/{user}/assign-roles', [UserController::class, 'assignRoles']);
    });

    // Role & Permission management – Admin only
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::post('/roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions']);
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/{permission}', [PermissionController::class, 'show']);
    });

    // Team: TL-only routes (controller checks role)
    Route::get('/teams/my-members', [\App\Http\Controllers\Api\TeamController::class, 'myMembers']);
    Route::get('/teams/my-members/{user}', [\App\Http\Controllers\Api\TeamController::class, 'myMemberDetail']);

    // Team management – Admin/PM only (list, create, update, delete, assign)
    Route::middleware(['role:admin,project_manager'])->group(function () {
        Route::apiResource('teams', \App\Http\Controllers\Api\TeamController::class);
        Route::get('/teams/{team}/statistics', [\App\Http\Controllers\Api\TeamController::class, 'statistics']);
        Route::post('/teams/{team}/assign-members', [\App\Http\Controllers\Api\TeamController::class, 'assignMembers']);
        Route::delete('/teams/{team}/members/{user}', [\App\Http\Controllers\Api\TeamController::class, 'removeMember']);
    });

    // Project Type management – Admin only
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('project-types', \App\Http\Controllers\Api\ProjectTypeController::class);
    });

    // Project management
    Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class);
    Route::get('/projects/{project}/history', [\App\Http\Controllers\Api\ProjectController::class, 'history']);
    Route::post('/projects/{project}/assign-pm', [\App\Http\Controllers\Api\ProjectController::class, 'assignToPM']);
    Route::post('/projects/{project}/assign-tl', [\App\Http\Controllers\Api\ProjectController::class, 'assignToTL']);
    Route::post('/projects/{project}/assign-employee', [\App\Http\Controllers\Api\ProjectController::class, 'assignToEmployee']);

    // Task management (specific routes before apiResource so /tasks/statistics is not caught as {task})
    Route::get('/tasks/statistics', [\App\Http\Controllers\Api\TaskController::class, 'statistics']);
    Route::apiResource('tasks', \App\Http\Controllers\Api\TaskController::class);
    Route::post('/tasks/{task}/assign', [\App\Http\Controllers\Api\TaskController::class, 'assign']);
    Route::patch('/tasks/{task}/status', [\App\Http\Controllers\Api\TaskController::class, 'updateStatus']);

    // Daily Plans (Morning Reports)
    Route::get('/daily-plans/assigned-tasks', [\App\Http\Controllers\Api\DailyPlanController::class, 'assignedTasks']);
    Route::get('/daily-plans/assigned-projects', [\App\Http\Controllers\Api\DailyPlanController::class, 'assignedProjects']);
    Route::get('/daily-plans/by-date', [\App\Http\Controllers\Api\DailyPlanController::class, 'byDate']);
    Route::get('/daily-plans/my-members', [\App\Http\Controllers\Api\DailyPlanController::class, 'myMembers']);
    Route::apiResource('daily-plans', \App\Http\Controllers\Api\DailyPlanController::class);
    Route::post('/daily-plans/{dailyPlan}/submit', [\App\Http\Controllers\Api\DailyPlanController::class, 'submit']);

    // EOD Reports
    Route::get('/eod-reports/my-members', [\App\Http\Controllers\Api\EodReportController::class, 'myMembers']);
    Route::apiResource('eod-reports', \App\Http\Controllers\Api\EodReportController::class);
    Route::post('/eod-reports/{eodReport}/submit', [\App\Http\Controllers\Api\EodReportController::class, 'submit']);

    // Report Approvals
    Route::get('/approvals/pending', [\App\Http\Controllers\Api\ReportApprovalController::class, 'pending']);
    Route::post('/eod-reports/{reportId}/approve', [\App\Http\Controllers\Api\ReportApprovalController::class, 'approve']);
    Route::post('/eod-reports/{reportId}/reject', [\App\Http\Controllers\Api\ReportApprovalController::class, 'reject']);

    // Attendance management (custom routes before apiResource)
    Route::get('/attendance/today', [\App\Http\Controllers\Api\AttendanceController::class, 'today']);
    Route::post('/attendance/check-in', [\App\Http\Controllers\Api\AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [\App\Http\Controllers\Api\AttendanceController::class, 'checkOut']);
    Route::get('/attendance/statistics', [\App\Http\Controllers\Api\AttendanceController::class, 'statistics']);
    Route::apiResource('attendance', \App\Http\Controllers\Api\AttendanceController::class);

    // Leave (anyone can apply; only Admin/HR can approve)
    Route::get('/leaves/dashboard', [\App\Http\Controllers\Api\LeaveController::class, 'dashboard']);
    Route::post('/leaves/{leave}/approve', [\App\Http\Controllers\Api\LeaveController::class, 'approve']);
    Route::post('/leaves/{leave}/reject', [\App\Http\Controllers\Api\LeaveController::class, 'reject']);
    Route::apiResource('leaves', \App\Http\Controllers\Api\LeaveController::class)->only(['index', 'store', 'show']);

    // Holiday management – Admin only
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('holidays', \App\Http\Controllers\Api\HolidayController::class);
    });

    // Time Entry management
    Route::apiResource('time-entries', \App\Http\Controllers\Api\TimeEntryController::class);
    Route::post('/time-entries/{timeEntry}/approve', [\App\Http\Controllers\Api\TimeEntryController::class, 'approve']);
    Route::post('/time-entries/{timeEntry}/reject', [\App\Http\Controllers\Api\TimeEntryController::class, 'reject']);

    // Analytics & Dashboard
    Route::get('/analytics/dashboard', [\App\Http\Controllers\Api\AnalyticsController::class, 'dashboard']);
    Route::get('/analytics/teams/{teamId}', [\App\Http\Controllers\Api\AnalyticsController::class, 'teamAnalytics']);
    Route::get('/analytics/employees/{userId}', [\App\Http\Controllers\Api\AnalyticsController::class, 'employeeAnalytics']);

    // Reports
    Route::get('/reports/teams/{teamId}/weekly', [\App\Http\Controllers\Api\ReportController::class, 'weeklyTeamReport']);
    Route::get('/reports/teams/{teamId}/monthly', [\App\Http\Controllers\Api\ReportController::class, 'monthlyTeamReport']);
    Route::get('/reports/teams/{teamId}/yearly', [\App\Http\Controllers\Api\ReportController::class, 'yearlyTeamReport']);
    Route::get('/reports/employees/{userId}/weekly', [\App\Http\Controllers\Api\ReportController::class, 'weeklyEmployeeReport']);
    Route::get('/reports/employees/{userId}/monthly', [\App\Http\Controllers\Api\ReportController::class, 'monthlyEmployeeReport']);
    Route::get('/reports/employees/{userId}/yearly', [\App\Http\Controllers\Api\ReportController::class, 'yearlyEmployeeReport']);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);
    Route::delete('/notifications/read/all', [\App\Http\Controllers\Api\NotificationController::class, 'deleteAllRead']);

    // Audit Logs – Admin only, stricter throttle for read-heavy endpoint
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index'])->middleware('throttle:30,1');
        Route::get('/audit-logs/{auditLog}', [\App\Http\Controllers\Api\AuditLogController::class, 'show'])->middleware('throttle:30,1');
    });

    // Export functionality
    Route::get('/export/teams/{teamId}/report/pdf', [\App\Http\Controllers\Api\ExportController::class, 'exportTeamReportPDF']);
    Route::get('/export/teams/{teamId}/report/csv', [\App\Http\Controllers\Api\ExportController::class, 'exportTeamReportCSV']);
    Route::get('/export/employees/{userId}/report/pdf', [\App\Http\Controllers\Api\ExportController::class, 'exportEmployeeReportPDF']);
    Route::get('/export/attendance/pdf', [\App\Http\Controllers\Api\ExportController::class, 'exportAttendancePDF']);
});