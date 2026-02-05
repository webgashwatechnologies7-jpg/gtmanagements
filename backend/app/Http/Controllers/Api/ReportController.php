<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use App\Models\Attendance;
use App\Models\EodReport;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ReportController extends Controller
{
    private function assertTeamLeadCanAccessTeam(int $teamId): void
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('team_lead')) {
            return;
        }
        $team = Team::where('team_lead_id', $user->id)->first();
        if (!$team || (int) $team->id !== (int) $teamId) {
            abort(403, 'Unauthorized');
        }
    }

    private function assertTeamLeadCanAccessEmployee(int $employeeId): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Unauthorized');
        }
        // Admin / PM can access any employee report
        if ($user->hasAnyRole(['admin', 'project_manager'])) {
            return;
        }
        if (! $user->hasRole('team_lead') && ! $user->hasRole('team_leader')) {
            // Employee can only access own
            if ((int) $employeeId !== (int) $user->id) {
                abort(403, 'Unauthorized');
            }
            return;
        }
        $team = Team::where('team_lead_id', $user->id)->first();
        $isMember = $team ? $team->members()->where('users.id', $employeeId)->exists() : false;
        if (! $isMember && (int) $employeeId !== (int) $user->id) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Generate weekly team report
     */
    public function weeklyTeamReport(Request $request, $teamId)
    {
        $this->assertTeamLeadCanAccessTeam((int) $teamId);
        $team = Team::findOrFail($teamId);
        $weekStart = $request->get('week_start', now()->startOfWeek()->format('Y-m-d'));
        $weekEnd = $request->get('week_end', now()->endOfWeek()->format('Y-m-d'));

        // Cache for 1 hour
        $cacheKey = 'report_team_weekly_' . $teamId . '_' . $weekStart . '_' . $weekEnd;
        
        $report = Cache::remember($cacheKey, 3600, function () use ($team, $weekStart, $weekEnd) {
            $teamMemberIds = $team->members()->pluck('users.id');

            return [
            'period' => [
                'type' => 'weekly',
                'start' => $weekStart,
                'end' => $weekEnd,
            ],
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'members_count' => $teamMemberIds->count(),
            ],
            'projects' => [
                'assigned' => Project::where('team_id', $team->id)->count(),
                'completed' => Project::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->whereBetween('completion_date', [$weekStart, $weekEnd])
                    ->count(),
                'in_progress' => Project::where('team_id', $team->id)
                    ->where('status', 'in_progress')
                    ->count(),
            ],
            'attendance' => [
                'present' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'present')
                    ->count(),
                'absent' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'absent')
                    ->count(),
            ],
            'time_tracking' => [
                'total_regular_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('entry_type', 'regular')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
                'total_overtime_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('entry_type', 'overtime')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ],
            'reports' => [
                'submitted' => EodReport::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'submitted')
                    ->count(),
                'approved' => EodReport::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'approved')
                    ->count(),
            ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Generate monthly team report
     */
    public function monthlyTeamReport(Request $request, $teamId)
    {
        $this->assertTeamLeadCanAccessTeam((int) $teamId);
        $team = Team::findOrFail($teamId);
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Cache for 1 hour
        $cacheKey = 'report_team_monthly_' . $teamId . '_' . $year . '_' . $month;
        
        $report = Cache::remember($cacheKey, 3600, function () use ($team, $month, $year) {
            $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
            $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

            $teamMemberIds = $team->members()->pluck('users.id');

            return [
            'period' => [
                'type' => 'monthly',
                'month' => $month,
                'year' => $year,
                'start' => $monthStart->format('Y-m-d'),
                'end' => $monthEnd->format('Y-m-d'),
            ],
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'members_count' => $teamMemberIds->count(),
            ],
            'projects' => [
                'assigned' => Project::where('team_id', $team->id)->count(),
                'completed' => Project::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->whereMonth('completion_date', $month)
                    ->whereYear('completion_date', $year)
                    ->count(),
                'in_progress' => Project::where('team_id', $team->id)
                    ->where('status', 'in_progress')
                    ->count(),
            ],
            'attendance' => [
                'present' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'present')
                    ->count(),
                'absent' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'absent')
                    ->count(),
            ],
            'time_tracking' => [
                'total_regular_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('entry_type', 'regular')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
                'total_overtime_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('entry_type', 'overtime')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ],
            'reports' => [
                'submitted' => EodReport::whereIn('user_id', $teamMemberIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'submitted')
                    ->count(),
                'approved' => EodReport::whereIn('user_id', $teamMemberIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'approved')
                    ->count(),
            ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Generate yearly team report
     */
    public function yearlyTeamReport(Request $request, $teamId)
    {
        $this->assertTeamLeadCanAccessTeam((int) $teamId);
        $team = Team::findOrFail($teamId);
        $year = $request->get('year', now()->year);

        // Cache for 1 hour
        $cacheKey = 'report_team_yearly_' . $teamId . '_' . $year;
        
        $report = Cache::remember($cacheKey, 3600, function () use ($team, $year) {
            $yearStart = Carbon::create($year, 1, 1)->startOfYear();
            $yearEnd = Carbon::create($year, 12, 31)->endOfYear();

            $teamMemberIds = $team->members()->pluck('users.id');

            return [
            'period' => [
                'type' => 'yearly',
                'year' => $year,
                'start' => $yearStart->format('Y-m-d'),
                'end' => $yearEnd->format('Y-m-d'),
            ],
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'members_count' => $teamMemberIds->count(),
            ],
            'projects' => [
                'assigned' => Project::where('team_id', $team->id)->count(),
                'completed' => Project::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->whereYear('completion_date', $year)
                    ->count(),
                'in_progress' => Project::where('team_id', $team->id)
                    ->where('status', 'in_progress')
                    ->count(),
            ],
            'attendance' => [
                'present' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereYear('date', $year)
                    ->where('status', 'present')
                    ->count(),
                'absent' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereYear('date', $year)
                    ->where('status', 'absent')
                    ->count(),
            ],
            'time_tracking' => [
                'total_regular_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereYear('date', $year)
                    ->where('entry_type', 'regular')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
                'total_overtime_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereYear('date', $year)
                    ->where('entry_type', 'overtime')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ],
            'reports' => [
                'submitted' => EodReport::whereIn('user_id', $teamMemberIds)
                    ->whereYear('date', $year)
                    ->where('status', 'submitted')
                    ->count(),
                'approved' => EodReport::whereIn('user_id', $teamMemberIds)
                    ->whereYear('date', $year)
                    ->where('status', 'approved')
                    ->count(),
            ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Generate weekly employee report
     */
    public function weeklyEmployeeReport(Request $request, $userId)
    {
        $this->assertTeamLeadCanAccessEmployee((int) $userId);
        $user = User::findOrFail($userId);
        $weekStart = $request->get('week_start', now()->startOfWeek()->format('Y-m-d'));
        $weekEnd = $request->get('week_end', now()->endOfWeek()->format('Y-m-d'));

        // Cache for 1 hour
        $cacheKey = 'report_employee_weekly_' . $userId . '_' . $weekStart . '_' . $weekEnd;
        
        $report = Cache::remember($cacheKey, 3600, function () use ($user, $weekStart, $weekEnd) {
            return [
            'period' => [
                'type' => 'weekly',
                'start' => $weekStart,
                'end' => $weekEnd,
            ],
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'projects' => [
                'assigned' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->count(),
                'completed' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->where('status', 'completed')
                    ->whereBetween('completion_date', [$weekStart, $weekEnd])
                    ->count(),
            ],
            'attendance' => [
                'present' => Attendance::where('user_id', $user->id)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'present')
                    ->count(),
                'absent' => Attendance::where('user_id', $user->id)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'absent')
                    ->count(),
            ],
            'time_tracking' => [
                'total_regular_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('entry_type', 'regular')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
                'total_overtime_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('entry_type', 'overtime')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ],
            'reports' => [
                'submitted' => EodReport::where('user_id', $user->id)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'submitted')
                    ->count(),
                'approved' => EodReport::where('user_id', $user->id)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'approved')
                    ->count(),
            ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Generate monthly employee report
     */
    public function monthlyEmployeeReport(Request $request, $userId)
    {
        $this->assertTeamLeadCanAccessEmployee((int) $userId);
        $user = User::findOrFail($userId);
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Cache for 1 hour
        $cacheKey = 'report_employee_monthly_' . $userId . '_' . $year . '_' . $month;
        
        $report = Cache::remember($cacheKey, 3600, function () use ($user, $month, $year) {
            $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
            $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

            return [
            'period' => [
                'type' => 'monthly',
                'month' => $month,
                'year' => $year,
                'start' => $monthStart->format('Y-m-d'),
                'end' => $monthEnd->format('Y-m-d'),
            ],
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'projects' => [
                'assigned' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->count(),
                'completed' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->where('status', 'completed')
                    ->whereMonth('completion_date', $month)
                    ->whereYear('completion_date', $year)
                    ->count(),
            ],
            'attendance' => [
                'present' => Attendance::where('user_id', $user->id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'present')
                    ->count(),
                'absent' => Attendance::where('user_id', $user->id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'absent')
                    ->count(),
            ],
            'time_tracking' => [
                'total_regular_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('entry_type', 'regular')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
                'total_overtime_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('entry_type', 'overtime')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ],
            'reports' => [
                'submitted' => EodReport::where('user_id', $user->id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'submitted')
                    ->count(),
                'approved' => EodReport::where('user_id', $user->id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'approved')
                    ->count(),
            ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Generate yearly employee report
     */
    public function yearlyEmployeeReport(Request $request, $userId)
    {
        $this->assertTeamLeadCanAccessEmployee((int) $userId);
        $user = User::findOrFail($userId);
        $year = $request->get('year', now()->year);

        // Cache for 1 hour
        $cacheKey = 'report_employee_yearly_' . $userId . '_' . $year;
        
        $report = Cache::remember($cacheKey, 3600, function () use ($user, $year) {
            $yearStart = Carbon::create($year, 1, 1)->startOfYear();
            $yearEnd = Carbon::create($year, 12, 31)->endOfYear();

            return [
            'period' => [
                'type' => 'yearly',
                'year' => $year,
                'start' => $yearStart->format('Y-m-d'),
                'end' => $yearEnd->format('Y-m-d'),
            ],
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'projects' => [
                'assigned' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->count(),
                'completed' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->where('status', 'completed')
                    ->whereYear('completion_date', $year)
                    ->count(),
            ],
            'attendance' => [
                'present' => Attendance::where('user_id', $user->id)
                    ->whereYear('date', $year)
                    ->where('status', 'present')
                    ->count(),
                'absent' => Attendance::where('user_id', $user->id)
                    ->whereYear('date', $year)
                    ->where('status', 'absent')
                    ->count(),
            ],
            'time_tracking' => [
                'total_regular_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereYear('date', $year)
                    ->where('entry_type', 'regular')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
                'total_overtime_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereYear('date', $year)
                    ->where('entry_type', 'overtime')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ],
            'reports' => [
                'submitted' => EodReport::where('user_id', $user->id)
                    ->whereYear('date', $year)
                    ->where('status', 'submitted')
                    ->count(),
                'approved' => EodReport::where('user_id', $user->id)
                    ->whereYear('date', $year)
                    ->where('status', 'approved')
                    ->count(),
            ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
