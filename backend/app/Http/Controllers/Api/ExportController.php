<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use App\Models\EodReport;
use App\Models\Attendance;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ExportController extends Controller
{
    /**
     * Export team report as PDF
     */
    public function exportTeamReportPDF(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);
        $period = $request->get('period', 'weekly'); // weekly, monthly, yearly
        $weekStart = $request->get('week_start', now()->startOfWeek()->format('Y-m-d'));
        $weekEnd = $request->get('week_end', now()->endOfWeek()->format('Y-m-d'));
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Get report data based on period
        $reportData = $this->getTeamReportData($team, $period, $weekStart, $weekEnd, $month, $year);

        $pdf = Pdf::loadView('exports.team-report', [
            'team' => $team,
            'period' => $period,
            'data' => $reportData,
            'generated_at' => now(),
        ]);

        return $pdf->download("team-report-{$team->name}-{$period}-{$year}.pdf");
    }

    /**
     * Export employee report as PDF
     */
    public function exportEmployeeReportPDF(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $period = $request->get('period', 'weekly');
        $weekStart = $request->get('week_start', now()->startOfWeek()->format('Y-m-d'));
        $weekEnd = $request->get('week_end', now()->endOfWeek()->format('Y-m-d'));
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        // Get report data
        $reportData = $this->getEmployeeReportData($user, $period, $weekStart, $weekEnd, $month, $year);

        $pdf = Pdf::loadView('exports.employee-report', [
            'employee' => $user,
            'period' => $period,
            'data' => $reportData,
            'generated_at' => now(),
        ]);

        return $pdf->download("employee-report-{$user->name}-{$period}-{$year}.pdf");
    }

    /**
     * Export attendance report as PDF
     */
    public function exportAttendancePDF(Request $request)
    {
        $userId = $request->get('user_id', Auth::id());
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $user = User::findOrFail($userId);
        
        $attendances = Attendance::where('user_id', $userId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'asc')
            ->get();

        $pdf = Pdf::loadView('exports.attendance', [
            'user' => $user,
            'month' => $month,
            'year' => $year,
            'attendances' => $attendances,
            'generated_at' => now(),
        ]);

        return $pdf->download("attendance-{$user->name}-{$month}-{$year}.pdf");
    }

    /**
     * Export team report as Excel (CSV)
     */
    public function exportTeamReportCSV(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);
        $period = $request->get('period', 'weekly');
        $weekStart = $request->get('week_start', now()->startOfWeek()->format('Y-m-d'));
        $weekEnd = $request->get('week_end', now()->endOfWeek()->format('Y-m-d'));
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $reportData = $this->getTeamReportData($team, $period, $weekStart, $weekEnd, $month, $year);

        $filename = "team-report-{$team->name}-{$period}-{$year}.csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($team, $period, $reportData) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, ['Team Report', $team->name, 'Period', $period]);
            fputcsv($file, []); // Empty row
            
            // Data rows
            fputcsv($file, ['Metric', 'Value']);
            foreach ($reportData as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        fputcsv($file, [ucfirst(str_replace('_', ' ', $key)) . ' - ' . ucfirst(str_replace('_', ' ', $subKey)), $subValue]);
                    }
                } else {
                    fputcsv($file, [ucfirst(str_replace('_', ' ', $key)), $value]);
                }
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get team report data
     */
    private function getTeamReportData($team, $period, $weekStart, $weekEnd, $month, $year)
    {
        $teamMemberIds = $team->members()->pluck('users.id');

        if ($period === 'weekly') {
            return [
                'period' => "{$weekStart} to {$weekEnd}",
                'members_count' => $teamMemberIds->count(),
                'projects_assigned' => Project::where('team_id', $team->id)->count(),
                'projects_completed' => Project::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->whereBetween('completion_date', [$weekStart, $weekEnd])
                    ->count(),
                'attendance_present' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'present')
                    ->count(),
                'total_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ];
        } elseif ($period === 'monthly') {
            return [
                'period' => "{$month}/{$year}",
                'members_count' => $teamMemberIds->count(),
                'projects_assigned' => Project::where('team_id', $team->id)->count(),
                'projects_completed' => Project::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->whereMonth('completion_date', $month)
                    ->whereYear('completion_date', $year)
                    ->count(),
                'attendance_present' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'present')
                    ->count(),
                'total_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ];
        } else {
            return [
                'period' => "{$year}",
                'members_count' => $teamMemberIds->count(),
                'projects_assigned' => Project::where('team_id', $team->id)->count(),
                'projects_completed' => Project::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->whereYear('completion_date', $year)
                    ->count(),
                'attendance_present' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereYear('date', $year)
                    ->where('status', 'present')
                    ->count(),
                'total_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereYear('date', $year)
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ];
        }
    }

    /**
     * Get employee report data
     */
    private function getEmployeeReportData($user, $period, $weekStart, $weekEnd, $month, $year)
    {
        if ($period === 'weekly') {
            return [
                'period' => "{$weekStart} to {$weekEnd}",
                'projects_assigned' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->count(),
                'attendance_present' => Attendance::where('user_id', $user->id)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'present')
                    ->count(),
                'total_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ];
        } elseif ($period === 'monthly') {
            return [
                'period' => "{$month}/{$year}",
                'projects_assigned' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->count(),
                'attendance_present' => Attendance::where('user_id', $user->id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'present')
                    ->count(),
                'total_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year)
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ];
        } else {
            return [
                'period' => "{$year}",
                'projects_assigned' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->count(),
                'attendance_present' => Attendance::where('user_id', $user->id)
                    ->whereYear('date', $year)
                    ->where('status', 'present')
                    ->count(),
                'total_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereYear('date', $year)
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ];
        }
    }
}
