<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyPlan\StoreDailyPlanRequest;
use App\Http\Resources\DailyPlanResource;
use App\Models\DailyPlan;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DailyPlanController extends Controller
{
    /**
     * Display a listing of daily plans
     */
    public function index(Request $request)
    {
        $query = DailyPlan::with(['user:id,name,email', 'items.project:id,name', 'items.task:id,name']);

        // Filter by user (for employees, only their own)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        } else {
            // If not admin/PM, only show own plans
            if (!Auth::user()->hasAnyRole(['admin', 'project_manager'])) {
                $query->where('user_id', Auth::id());
            }
        }

        // Filter by date
        if ($request->has('date')) {
            $query->where('date', $request->date);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $plans = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DailyPlanResource::collection($plans->items()),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    /**
     * Assigned tasks for current user (for daily plan – all assigned tasks)
     */
    public function assignedTasks(Request $request)
    {
        $user = Auth::user();
        $query = Task::with(['project:id,name'])
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('assigned_to_user_id', $user->id);
            })
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline', 'asc');

        $tasks = $query->limit(100)->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'project_id' => $t->project_id,
                'project' => $t->project ? ['id' => $t->project->id, 'name' => $t->project->name] : null,
                'priority' => $t->priority,
                'status' => $t->status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Assigned projects for daily plan dropdown (project_assignments + tasks)
     */
    public function assignedProjects(Request $request)
    {
        $user = Auth::user();
        $projectIds = collect();

        if ($user->hasRole('admin')) {
            $projects = Project::select('id', 'name')->whereNotIn('status', ['cancelled'])->orderBy('name')->get();
            return response()->json(['success' => true, 'data' => $projects]);
        }

        if ($user->hasRole('project_manager')) {
            $projectIds = $projectIds->merge(Project::where('project_manager_id', $user->id)->pluck('id'));
        }
        if ($user->hasRole('team_lead') || $user->hasRole('team_leader')) {
            $projectIds = $projectIds->merge(
                \App\Models\ProjectAssignment::where('assignment_level', 'tl')
                    ->where('status', 'active')
                    ->where('assigned_to_user_id', $user->id)
                    ->pluck('project_id')
            );
        }
        $projectIds = $projectIds->merge(
            \App\Models\ProjectAssignment::where('status', 'active')
                ->where('assigned_to_user_id', $user->id)
                ->pluck('project_id')
        );
        $projectIds = $projectIds->merge(
            Task::where(function ($q) use ($user) {
                $q->where('created_by', $user->id)->orWhere('assigned_to_user_id', $user->id);
            })->pluck('project_id')
        );

        $projectIds = $projectIds->unique()->filter()->values();
        $projects = Project::select('id', 'name')
            ->whereIn('id', $projectIds)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $projects]);
    }

    /**
     * Current user's daily plan for a given date (for EOD – today's plan tasks)
     */
    public function byDate(Request $request)
    {
        $request->validate(['date' => 'required|date']);
        $plan = DailyPlan::with(['items.project:id,name', 'items.task:id,name'])
            ->where('user_id', Auth::id())
            ->where('date', $request->date)
            ->first();
        return response()->json([
            'success' => true,
            'data' => $plan ? new DailyPlanResource($plan) : null,
        ]);
    }

    /**
     * TL: List team members' daily plans (jis ke report aye h)
     */
    public function myMembers(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasAnyRole(['team_lead', 'team_leader'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $team = \App\Models\Team::where('team_lead_id', $user->id)->first();
        if (!$team) {
            $team = \App\Models\Team::whereHas('members', function ($q) use ($user) {
                $q->where('users.id', $user->id)->where('team_members.status', 'active');
            })->first();
        }

        if (!$team) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0],
            ]);
        }

        $teamMemberIds = $team->members()
            ->wherePivot('status', 'active')
            ->where('users.id', '!=', $user->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'employee'))
            ->pluck('users.id');

        $query = DailyPlan::with(['user:id,name,email', 'items.project:id,name', 'items.task:id,name'])
            ->whereIn('user_id', $teamMemberIds);

        if ($request->has('date')) {
            $query->where('date', $request->date);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $plans = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DailyPlanResource::collection($plans->items()),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    /**
     * Store a newly created daily plan (sirf aaj ki date – na aage na piche)
     */
    public function store(StoreDailyPlanRequest $request)
    {
        $planData = $request->validated();
        $today = now()->format('Y-m-d');
        if (($planData['date'] ?? '') !== $today) {
            return response()->json([
                'success' => false,
                'message' => 'Daily plan can only be created for today\'s date. Past or future dates are not allowed.',
            ], 422);
        }
        $planData['user_id'] = Auth::id();
        $planData['status'] = 'draft';

        $items = $planData['items'] ?? [];
        unset($planData['items']);

        $user = Auth::user();
        foreach ($items as $item) {
            if (!empty($item['task_id'])) {
                $task = Task::find($item['task_id']);
                if (!$task || ($task->created_by !== $user->id && $task->assigned_to_user_id !== $user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: task does not belong to you.',
                    ], 403);
                }
            }
        }

        $dailyPlan = DailyPlan::create($planData);

        foreach ($items as $item) {
            $taskId = $item['task_id'] ?? null;
            if (empty($taskId)) {
                // Auto-create task in selected project (name + full description required)
                $taskName = !empty($item['task_name'])
                    ? \Illuminate\Support\Str::limit($item['task_name'], 200)
                    : \Illuminate\Support\Str::limit($item['description'] ?? 'Daily plan task', 200);
                $task = Task::create([
                    'project_id' => $item['project_id'],
                    'name' => $taskName,
                    'description' => $item['description'] ?? null,
                    'assigned_to_user_id' => $user->id,
                    'created_by' => $user->id,
                    'priority' => $item['priority'] ?? 'medium',
                    'status' => 'in_progress',
                    'estimated_hours' => $item['planned_hours'] ?? null,
                ]);
                $taskId = $task->id;
            }
            $dailyPlan->items()->create([
                'project_id' => $item['project_id'],
                'task_id' => $taskId,
                'planned_hours' => $item['planned_hours'],
                'description' => $item['description'],
                'priority' => $item['priority'] ?? 'medium',
            ]);
        }

        $dailyPlan->load(['user', 'items.project', 'items.task']);

        return response()->json([
            'success' => true,
            'message' => 'Daily plan created successfully',
            'data' => new DailyPlanResource($dailyPlan),
        ], 201);
    }

    /**
     * Display the specified daily plan
     */
    public function show(DailyPlan $dailyPlan)
    {
        $user = Auth::user();
        if ($dailyPlan->user_id === $user->id) {
            // Owner can view
        } elseif ($user->hasAnyRole(['admin', 'project_manager'])) {
            // Admin/PM can view any
        } elseif ($user->hasAnyRole(['team_lead', 'team_leader'])) {
            // TL can view team members' plans
            $team = \App\Models\Team::where('team_lead_id', $user->id)->first();
            if (!$team) {
                $team = \App\Models\Team::whereHas('members', function ($q) use ($user) {
                    $q->where('users.id', $user->id)->where('team_members.status', 'active');
                })->first();
            }
            if (!$team || !$team->members()->wherePivot('status', 'active')->where('users.id', $dailyPlan->user_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $dailyPlan->load(['user', 'items.project', 'items.task']);

        return response()->json([
            'success' => true,
            'data' => new DailyPlanResource($dailyPlan),
        ]);
    }

    /**
     * Update the specified daily plan
     */
    public function update(StoreDailyPlanRequest $request, DailyPlan $dailyPlan)
    {
        // Check if user can update this plan
        if ($dailyPlan->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Can't update if already submitted
        if ($dailyPlan->status === 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update submitted plan',
            ], 422);
        }

        $planData = $request->validated();
        $items = $planData['items'] ?? [];
        unset($planData['items']);

        $dailyPlan->update($planData);

        // Update items (delete old and create new)
        $dailyPlan->items()->delete();
        $user = Auth::user();
        foreach ($items as $item) {
            $taskId = $item['task_id'] ?? null;
            if (empty($taskId)) {
                $taskName = !empty($item['task_name'])
                    ? \Illuminate\Support\Str::limit($item['task_name'], 200)
                    : \Illuminate\Support\Str::limit($item['description'] ?? 'Daily plan task', 200);
                $task = Task::create([
                    'project_id' => $item['project_id'],
                    'name' => $taskName,
                    'description' => $item['description'] ?? null,
                    'assigned_to_user_id' => $user->id,
                    'created_by' => $user->id,
                    'priority' => $item['priority'] ?? 'medium',
                    'status' => 'in_progress',
                    'estimated_hours' => $item['planned_hours'] ?? null,
                ]);
                $taskId = $task->id;
            }
            $dailyPlan->items()->create([
                'project_id' => $item['project_id'],
                'task_id' => $taskId,
                'planned_hours' => $item['planned_hours'],
                'description' => $item['description'],
                'priority' => $item['priority'] ?? 'medium',
            ]);
        }

        $dailyPlan->load(['user', 'items.project', 'items.task']);

        return response()->json([
            'success' => true,
            'message' => 'Daily plan updated successfully',
            'data' => new DailyPlanResource($dailyPlan),
        ]);
    }

    /**
     * Submit daily plan
     */
    public function submit(DailyPlan $dailyPlan)
    {
        if ($dailyPlan->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($dailyPlan->status === 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Plan already submitted',
            ], 422);
        }

        $dailyPlan->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Attendance ab check-in/check-out se create hoti hai; daily plan submit se auto-create comment out
        // $attendanceController = new \App\Http\Controllers\Api\AttendanceController();
        // $attendanceController->createFromDailyPlan($dailyPlan->user_id, $dailyPlan->date->format('Y-m-d'));

        return response()->json([
            'success' => true,
            'message' => 'Daily plan submitted successfully',
            'data' => new DailyPlanResource($dailyPlan->load(['user', 'items.project', 'items.task'])),
        ]);
    }

    /**
     * Remove the specified daily plan
     */
    public function destroy(DailyPlan $dailyPlan)
    {
        if ($dailyPlan->user_id !== Auth::id() && !Auth::user()->hasAnyRole(['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $dailyPlan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Daily plan deleted successfully',
        ]);
    }
}
