<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Attendance;
use App\Models\EodReport;
use App\Models\TimeEntry;
use App\Models\DailyPlan;
use App\Models\ProjectAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Get dashboard statistics for current user based on role
     */
    public function dashboard(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            return $this->adminDashboard();
        } elseif ($user->hasRole('project_manager')) {
            return $this->projectManagerDashboard($user);
        } elseif ($user->hasRole('team_lead')) {
            return $this->teamLeadDashboard($user);
        } else {
            return $this->employeeDashboard($user);
        }
    }

    /**
     * Admin Dashboard Statistics
     * Projects by status: working (active), not_started (planning), waiting (on_hold), completed.
     * Teams report: per team - projects assigned, completed, completed before deadline.
     */
    private function adminDashboard()
    {
        $cacheKey = 'dashboard_admin_' . Auth::id();

        $stats = Cache::remember($cacheKey, 600, function () {
            $totalProjects = Project::count();
            $projectsWorking = Project::where('status', 'active')->count();
            $projectsNotStarted = Project::where('status', 'planning')->count();
            $projectsWaiting = Project::where('status', 'on_hold')->count();
            $projectsCompleted = Project::where('status', 'completed')->count();
            $totalTeams = Team::where('status', 'active')->count();

            $teams = Team::where('status', 'active')
                ->withCount(['members as members_count' => function ($q) {
                    $q->where('team_members.status', 'active');
                }])
                ->orderBy('name')
                ->get();
            $teamsSummary = [];
            foreach ($teams as $team) {
                $assigned = Project::where('team_id', $team->id)->count();
                $completed = Project::where('team_id', $team->id)->where('status', 'completed')->count();
                $completedBeforeDeadline = Project::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->whereNotNull('deadline')
                    ->whereNotNull('completion_date')
                    ->whereColumn('completion_date', '<=', 'deadline')
                    ->count();
                $teamsSummary[] = [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members_count' => (int) ($team->members_count ?? 0),
                    'projects_assigned' => $assigned,
                    'projects_completed' => $completed,
                    'projects_completed_before_deadline' => $completedBeforeDeadline,
                ];
            }

            // Project assignments: which project is assigned to whom (for admin)
            $assignments = ProjectAssignment::where('status', 'active')
                ->with(['project:id,name,status', 'assignedTo:id,name,email'])
                ->orderBy('project_id')
                ->get()
                ->map(function ($a) {
                    return [
                        'project_id' => $a->project_id,
                        'project_name' => $a->project?->name,
                        'project_status' => $a->project?->status,
                        'assigned_to_user_id' => $a->assigned_to_user_id,
                        'assigned_to_name' => $a->assignedTo?->name,
                        'assignment_level' => $a->assignment_level,
                    ];
                })
                ->values()
                ->all();

            // Who is working on which project today (today's submitted daily plans)
            $todayPlans = DailyPlan::where('date', today())
                ->where('status', 'submitted')
                ->with(['user:id,name,email', 'items.project:id,name'])
                ->orderBy('submitted_at', 'desc')
                ->get();
            $whoWorkingToday = $todayPlans->map(function ($plan) {
                $projectNames = $plan->items->pluck('project')->filter()->pluck('name')->unique()->values()->all();
                return [
                    'user_id' => $plan->user_id,
                    'user_name' => $plan->user?->name,
                    'plan_id' => $plan->id,
                    'plan_date' => $plan->date?->format('Y-m-d'),
                    'submitted_at' => $plan->submitted_at?->format('Y-m-d H:i:s'),
                    'projects' => array_values($projectNames),
                ];
            })->values()->all();

            return [
                'total_projects' => $totalProjects,
                'projects_working' => $projectsWorking,
                'projects_not_started' => $projectsNotStarted,
                'projects_waiting' => $projectsWaiting,
                'projects_completed' => $projectsCompleted,
                'total_teams' => $totalTeams,
                'teams_summary' => $teamsSummary,
                'total_users' => User::where('status', 'active')->count(),
                'pending_approvals' => \App\Models\Approval::where('status', 'pending')->count(),
                'today_attendance' => Attendance::where('date', today())->where('status', 'present')->count(),
                'this_month_attendance_rate' => $this->calculateMonthlyAttendanceRate(),
                'project_assignments' => $assignments,
                'who_working_today' => $whoWorkingToday,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Shared project & team report – shown on dashboard for all roles
     */
    private function getProjectAndTeamReport(): array
    {
        return Cache::remember('dashboard_project_team_report', 600, function () {
            $totalProjects = Project::count();
            $projectsWorking = Project::where('status', 'active')->count();
            $projectsNotStarted = Project::where('status', 'planning')->count();
            $projectsWaiting = Project::where('status', 'on_hold')->count();
            $projectsCompleted = Project::where('status', 'completed')->count();
            $totalTeams = Team::where('status', 'active')->count();

            $teams = Team::where('status', 'active')
                ->withCount(['members as members_count' => function ($q) {
                    $q->where('team_members.status', 'active');
                }])
                ->orderBy('name')
                ->get();
            $teamsSummary = [];
            foreach ($teams as $team) {
                $assigned = Project::where('team_id', $team->id)->count();
                $completed = Project::where('team_id', $team->id)->where('status', 'completed')->count();
                $completedBeforeDeadline = Project::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->whereNotNull('deadline')
                    ->whereNotNull('completion_date')
                    ->whereColumn('completion_date', '<=', 'deadline')
                    ->count();
                $teamsSummary[] = [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members_count' => (int) ($team->members_count ?? 0),
                    'projects_assigned' => $assigned,
                    'projects_completed' => $completed,
                    'projects_completed_before_deadline' => $completedBeforeDeadline,
                ];
            }

            return [
                'total_projects' => $totalProjects,
                'projects_working' => $projectsWorking,
                'projects_not_started' => $projectsNotStarted,
                'projects_waiting' => $projectsWaiting,
                'projects_completed' => $projectsCompleted,
                'total_teams' => $totalTeams,
                'teams_summary' => $teamsSummary,
            ];
        });
    }

    /**
     * Project Manager Dashboard Statistics
     */
    private function projectManagerDashboard($user)
    {
        $cacheKey = 'dashboard_pm_' . $user->id;

        $roleStats = Cache::remember($cacheKey, 600, function () use ($user) {
            $assignedProjects = Project::where('project_manager_id', $user->id)->get();
            $projectIds = $assignedProjects->pluck('id');

            return [
                'assigned_projects' => $assignedProjects->count(),
                'active_projects' => $assignedProjects->where('status', 'active')->count(),
                'completed_projects' => $assignedProjects->where('status', 'completed')->count(),
                'pending_approvals' => \App\Models\Approval::whereHas('approver', function ($q) use ($user) {
                    $q->where('id', $user->id);
                })->where('status', 'pending')->count(),
                'team_members' => User::whereHas('teams', function ($q) use ($user) {
                    $q->where('project_manager_id', $user->id);
                })->count(),
                'this_month_time_spent' => TimeEntry::whereIn('project_id', $projectIds)
                    ->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year)
                    ->where('status', 'approved')
                    ->sum('minutes'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $roleStats,
        ]);
    }

    /**
     * Team Lead Dashboard Statistics
     */
    private function teamLeadDashboard($user)
    {
        $cacheKey = 'dashboard_tl_' . $user->id;

        $roleStats = Cache::remember($cacheKey, 600, function () use ($user) {
            $team = Team::where('team_lead_id', $user->id)->first();

            if (!$team) {
                return [
                    'team_members' => 0,
                    'assigned_projects' => 0,
                    'active_projects' => 0,
                    'completed_projects' => 0,
                    'pending_approvals' => 0,
                    'team' => null,
                    'assigned_projects_list' => [],
                ];
            }

            $teamMemberIds = $team->members()->pluck('users.id');

            // TL assigned projects are tracked via project_assignments
            $assignedProjectsQuery = Project::whereHas('assignments', function ($q) use ($user) {
                $q->where('assignment_level', 'tl')
                    ->where('status', 'active')
                    ->where('assigned_to_user_id', $user->id);
            });

            $assignedProjectsList = \App\Models\ProjectAssignment::query()
                ->where('assignment_level', 'tl')
                ->where('status', 'active')
                ->where('assigned_to_user_id', $user->id)
                ->with(['project:id,name,status'])
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->take(50)
                ->get()
                ->map(function ($a) {
                    return [
                        'id' => $a->project?->id,
                        'name' => $a->project?->name,
                        'status' => $a->project?->status,
                        'assigned_at' => $a->assigned_at?->format('Y-m-d H:i:s'),
                    ];
                })
                ->filter(fn ($row) => !empty($row['id']) && !empty($row['name']))
                ->values()
                ->all();

            return [
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'team_members' => $teamMemberIds->count(),
                'assigned_projects' => (clone $assignedProjectsQuery)->count(),
                'active_projects' => (clone $assignedProjectsQuery)->where('status', 'active')->count(),
                'completed_projects' => (clone $assignedProjectsQuery)->where('status', 'completed')->count(),
                'pending_approvals' => \App\Models\Approval::whereHas('approver', function ($q) use ($user) {
                    $q->where('id', $user->id);
                })->where('status', 'pending')->count(),
                'today_team_attendance' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->where('date', today())
                    ->where('status', 'present')
                    ->count(),
                'this_week_team_reports' => EodReport::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
                'assigned_projects_list' => $assignedProjectsList,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $roleStats,
        ]);
    }

    /**
     * Employee Dashboard Statistics
     */
    private function employeeDashboard($user)
    {
        $cacheKey = 'dashboard_employee_' . $user->id;

        $roleStats = Cache::remember($cacheKey, 600, function () use ($user) {
            $tasksCreated = Task::where('created_by', $user->id)->count();
            $tasksCompletedToday = Task::where(function ($q) use ($user) {
                $q->where('created_by', $user->id)->orWhere('assigned_to_user_id', $user->id);
            })
                ->where('status', 'completed')
                ->whereDate('updated_at', now()->toDateString())
                ->count();
            $tasksPending = Task::where(function ($q) use ($user) {
                $q->where('created_by', $user->id)->orWhere('assigned_to_user_id', $user->id);
            })
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();

            $myTasksList = Task::with(['project:id,name', 'assignedTo:id,name'])
                ->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhere('assigned_to_user_id', $user->id);
                })
                ->whereNotIn('status', ['cancelled'])
                ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END, deadline ASC')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'status' => $t->status,
                        'deadline' => $t->deadline ? $t->deadline->format('Y-m-d') : null,
                        'project' => $t->project ? ['id' => $t->project->id, 'name' => $t->project->name] : null,
                    ];
                });

            return [
                'assigned_projects' => Project::whereHas('assignments', function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id);
                })->count(),
                'this_month_time_spent' => TimeEntry::where('user_id', $user->id)
                    ->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year)
                    ->where('status', 'approved')
                    ->sum('minutes'),
                'this_month_attendance' => Attendance::where('user_id', $user->id)
                    ->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year)
                    ->where('status', 'present')
                    ->count(),
                'pending_reports' => EodReport::where('user_id', $user->id)
                    ->where('status', 'draft')
                    ->count(),
                'tasks_created_count' => $tasksCreated,
                'tasks_completed_today' => $tasksCompletedToday,
                'tasks_pending' => $tasksPending,
                'my_tasks_list' => $myTasksList,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $roleStats,
        ]);
    }

    /**
     * Calculate monthly attendance rate
     */
    private function calculateMonthlyAttendanceRate()
    {
        $totalDays = now()->daysInMonth;
        $presentDays = Attendance::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->where('status', 'present')
            ->distinct('user_id', 'date')
            ->count();

        $totalUsers = User::where('status', 'active')->count();
        $expectedDays = $totalUsers * $totalDays;

        return $expectedDays > 0 ? round(($presentDays / $expectedDays) * 100, 2) : 0;
    }

    /**
     * Get team analytics
     */
    public function teamAnalytics(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);
        $teamMemberIds = $team->members()->pluck('users.id');

        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->endOfMonth()->format('Y-m-d'));

        $analytics = [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'members_count' => $teamMemberIds->count(),
            ],
            'projects' => [
                'assigned' => Project::where('team_id', $team->id)->count(),
                'completed' => Project::where('team_id', $team->id)
                    ->where('status', 'completed')
                    ->count(),
                'in_progress' => Project::where('team_id', $team->id)
                    ->where('status', 'in_progress')
                    ->count(),
            ],
            'attendance' => [
                'present' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('status', 'present')
                    ->count(),
                'absent' => Attendance::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('status', 'absent')
                    ->count(),
            ],
            'time_tracking' => [
                'total_regular_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('entry_type', 'regular')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
                'total_overtime_hours' => round(TimeEntry::whereIn('user_id', $teamMemberIds)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('entry_type', 'overtime')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get employee analytics
     */
    public function employeeAnalytics(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->endOfMonth()->format('Y-m-d'));

        $analytics = [
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
                })->where('status', 'completed')->count(),
            ],
            'attendance' => [
                'present' => Attendance::where('user_id', $user->id)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('status', 'present')
                    ->count(),
                'absent' => Attendance::where('user_id', $user->id)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('status', 'absent')
                    ->count(),
            ],
            'time_tracking' => [
                'total_regular_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('entry_type', 'regular')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
                'total_overtime_hours' => round(TimeEntry::where('user_id', $user->id)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('entry_type', 'overtime')
                    ->where('status', 'approved')
                    ->sum('minutes') / 60, 2),
            ],
            'reports' => [
                'submitted' => EodReport::where('user_id', $user->id)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('status', 'submitted')
                    ->count(),
                'approved' => EodReport::where('user_id', $user->id)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->where('status', 'approved')
                    ->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }
}
