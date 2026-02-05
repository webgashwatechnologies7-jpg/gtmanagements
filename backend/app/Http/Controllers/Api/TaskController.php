<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\Project;
use App\Helpers\CacheHelper;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks
     */
    public function index(Request $request)
    {
        $query = Task::with(['project', 'assignedTo', 'creator']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by project
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by assigned user
        if ($request->has('assigned_to_user_id')) {
            $query->where('assigned_to_user_id', $request->assigned_to_user_id);
        }

        // Filter by created_by
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Role-based filtering
        $user = Auth::user();
        if ($user->hasRole('employee')) {
            // Employee: tasks assigned to them + tasks they created (unassigned should also be visible)
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to_user_id', $user->id)
                  ->orWhere('created_by', $user->id);
            });
        } elseif ($user->hasRole('team_lead')) {
            // Team Leads can see tasks for their team's projects
            $teamIds = $user->teams()->pluck('teams.id');
            $projectIds = Project::whereIn('team_id', $teamIds)->pluck('id');
            $query->whereIn('project_id', $projectIds);
        } elseif ($user->hasRole('project_manager')) {
            // PMs can see tasks for their assigned projects
            $projectIds = Project::where('project_manager_id', $user->id)->pluck('id');
            $query->whereIn('project_id', $projectIds);
        }
        // Admin can see all tasks (no filter)

        // Pagination
        $perPage = $request->get('per_page', 15);
        $tasks = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => TaskResource::collection($tasks->items()),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    /**
     * Store a newly created task
     */
    public function store(StoreTaskRequest $request)
    {
        $user = Auth::user();
        $taskData = $request->validated();

        // Employee: can create task only for projects they are assigned to
        if ($user->hasRole('employee')) {
            $project = Project::find($taskData['project_id'] ?? null);
            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Project not found.'], 404);
            }
            $isAssigned = $project->assignments()
                ->where('status', 'active')
                ->where('assigned_to_user_id', $user->id)
                ->exists();
            if (!$isAssigned) {
                return response()->json(['success' => false, 'message' => 'You can only create tasks for projects assigned to you.'], 403);
            }
        }

        $taskData['created_by'] = Auth::id();
        $taskData['status'] = $taskData['status'] ?? 'todo';
        $taskData['actual_hours'] = 0;

        $task = Task::create($taskData);
        $task->load(['project', 'assignedTo', 'creator']);

        // Log creation
        try {
            AuditLogService::logCreate('task', $task->id, $task->toArray(), $request);
            // Also in project history: who created the task, when
            AuditLogService::log('task_created', 'project', $task->project_id, null, [
                'task_id' => $task->id,
                'task_name' => $task->name,
            ], $request);
        } catch (\Exception $e) {
            \Log::error('Failed to log task creation: ' . $e->getMessage());
        }

        // Send notification to assigned user
        if ($task->assigned_to_user_id) {
            try {
                NotificationService::send(
                    $task->assigned_to_user_id,
                    'Task Assigned',
                    "You have been assigned a new task: {$task->name}",
                    'task_assigned',
                    ['task_id' => $task->id, 'project_id' => $task->project_id]
                );
            } catch (\Exception $e) {
                \Log::error('Failed to send task assignment notification: ' . $e->getMessage());
            }
        }

        CacheHelper::invalidateTaskStatistics();
        \App\Helpers\CacheHelper::clearDashboardCache(Auth::id());
        if ($task->assigned_to_user_id) {
            \App\Helpers\CacheHelper::clearDashboardCache($task->assigned_to_user_id);
        }
        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => new TaskResource($task),
        ], 201);
    }

    /**
     * Display the specified task
     */
    public function show(Task $task)
    {
        $task->load(['project', 'assignedTo', 'creator']);

        return response()->json([
            'success' => true,
            'data' => new TaskResource($task),
        ]);
    }

    /**
     * Update the specified task
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $oldData = $task->toArray();
        $task->update($request->validated());
        $task->load(['project', 'assignedTo', 'creator']);

        // Log update
        try {
            AuditLogService::logUpdate('task', $task->id, $oldData, $task->toArray(), $request);
            // Project history: who edited the task
            $task->refresh();
            AuditLogService::log('task_updated', 'project', $task->project_id, $oldData, [
                'task_id' => $task->id,
                'task_name' => $task->name,
                'status' => $task->status,
                'priority' => $task->priority,
            ], $request);
        } catch (\Exception $e) {
            \Log::error('Failed to log task update: ' . $e->getMessage());
        }

        // Send notification if status changed to completed
        if ($request->has('status') && $request->status === 'completed' && $oldData['status'] !== 'completed') {
            try {
                NotificationService::send(
                    $task->created_by,
                    'Task Completed',
                    "Task '{$task->name}' has been marked as completed",
                    'task_completed',
                    ['task_id' => $task->id, 'project_id' => $task->project_id]
                );
            } catch (\Exception $e) {
                \Log::error('Failed to send task completion notification: ' . $e->getMessage());
            }
        }

        CacheHelper::invalidateTaskStatistics();
        \App\Helpers\CacheHelper::clearDashboardCache(Auth::id());
        if ($task->created_by) {
            \App\Helpers\CacheHelper::clearDashboardCache($task->created_by);
        }
        if ($task->assigned_to_user_id) {
            \App\Helpers\CacheHelper::clearDashboardCache($task->assigned_to_user_id);
        }
        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => new TaskResource($task),
        ]);
    }

    /**
     * Remove the specified task
     */
    public function destroy(Task $task)
    {
        $oldData = $task->toArray();
        $projectId = $task->project_id;
        $taskId = $task->id;
        $taskName = $task->name;
        $task->delete();

        // Log deletion
        try {
            AuditLogService::logDelete('task', $taskId, $oldData, request());
            // Project history: who deleted the task
            AuditLogService::log('task_deleted', 'project', $projectId, [
                'task_id' => $taskId,
                'task_name' => $taskName,
            ], null, request());
        } catch (\Exception $e) {
            \Log::error('Failed to log task deletion: ' . $e->getMessage());
        }

        CacheHelper::invalidateTaskStatistics();
        \App\Helpers\CacheHelper::clearDashboardCache(Auth::id());
        if ($oldData['created_by'] ?? null) {
            \App\Helpers\CacheHelper::clearDashboardCache($oldData['created_by']);
        }
        if ($oldData['assigned_to_user_id'] ?? null) {
            \App\Helpers\CacheHelper::clearDashboardCache($oldData['assigned_to_user_id']);
        }
        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully',
        ]);
    }

    /**
     * Assign task to a user
     */
    public function assign(Request $request, Task $task)
    {
        $request->validate([
            'assigned_to_user_id' => 'required|exists:users,id',
        ]);

        $oldData = $task->toArray();
        $task->update([
            'assigned_to_user_id' => $request->assigned_to_user_id,
        ]);
        $task->load(['project', 'assignedTo', 'creator']);

        // Log assignment
        try {
            AuditLogService::logUpdate('task', $task->id, $oldData, $task->toArray(), $request);
        } catch (\Exception $e) {
            \Log::error('Failed to log task assignment: ' . $e->getMessage());
        }

        // Send notification
        try {
            NotificationService::send(
                $request->assigned_to_user_id,
                'Task Assigned',
                "You have been assigned a new task: {$task->name}",
                'task_assigned',
                ['task_id' => $task->id, 'project_id' => $task->project_id]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send task assignment notification: ' . $e->getMessage());
        }

        CacheHelper::invalidateTaskStatistics();
        \App\Helpers\CacheHelper::clearDashboardCache(Auth::id());
        \App\Helpers\CacheHelper::clearDashboardCache($request->assigned_to_user_id);
        return response()->json([
            'success' => true,
            'message' => 'Task assigned successfully',
            'data' => new TaskResource($task),
        ]);
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, Task $task)
    {
        $request->validate([
            'status' => ['required', 'in:todo,in_progress,review,completed,cancelled'],
            'completion_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $oldData = $task->toArray();
        $updateData = ['status' => $request->status];
        if ($request->has('completion_notes')) {
            $updateData['completion_notes'] = $request->completion_notes;
        }
        $task->update($updateData);
        $task->load(['project', 'assignedTo', 'creator']);

        // Log status update
        try {
            AuditLogService::logUpdate('task', $task->id, $oldData, $task->toArray(), $request);
            // Project history: who changed task status (complete / in progress etc)
            AuditLogService::log('task_status_changed', 'project', $task->project_id, null, [
                'task_id' => $task->id,
                'task_name' => $task->name,
                'old_status' => $oldData['status'] ?? null,
                'new_status' => $request->status,
            ], $request);
        } catch (\Exception $e) {
            \Log::error('Failed to log task status update: ' . $e->getMessage());
        }

        // Send notification if completed
        if ($request->status === 'completed' && $oldData['status'] !== 'completed') {
            try {
                NotificationService::send(
                    $task->created_by,
                    'Task Completed',
                    "Task '{$task->name}' has been marked as completed",
                    'task_completed',
                    ['task_id' => $task->id, 'project_id' => $task->project_id]
                );
            } catch (\Exception $e) {
                \Log::error('Failed to send task completion notification: ' . $e->getMessage());
            }
        }

        CacheHelper::invalidateTaskStatistics();
        \App\Helpers\CacheHelper::clearDashboardCache(Auth::id());
        if ($task->created_by) {
            \App\Helpers\CacheHelper::clearDashboardCache($task->created_by);
        }
        if ($task->assigned_to_user_id) {
            \App\Helpers\CacheHelper::clearDashboardCache($task->assigned_to_user_id);
        }
        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'data' => new TaskResource($task),
        ]);
    }

    /**
     * Get task statistics (cached 5 min, invalidated on task changes).
     * Uses single aggregated query for performance.
     */
    public function statistics(Request $request)
    {
        $user = Auth::user();
        $projectId = $request->get('project_id');
        $version = Cache::get('task_stats_version', 0);
        $cacheKey = 'task_stats_' . $version . '_' . $user->id . '_' . ($projectId ?? 'all');

        $stats = Cache::remember($cacheKey, 300, function () use ($request, $user) {
            $query = Task::query();

            if ($request->has('project_id')) {
                $query->where('project_id', $request->project_id);
            }

            if ($user->hasRole('employee')) {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id)
                      ->orWhere('created_by', $user->id);
                });
            } elseif ($user->hasRole('team_lead')) {
                $teamIds = $user->teams()->pluck('teams.id');
                $projectIds = Project::whereIn('team_id', $teamIds)->pluck('id');
                $query->whereIn('project_id', $projectIds);
            } elseif ($user->hasRole('project_manager')) {
                $projectIds = Project::where('project_manager_id', $user->id)->pluck('id');
                $query->whereIn('project_id', $projectIds);
            }

            $row = $query->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority,
                SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium_priority,
                SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_priority,
                COALESCE(SUM(estimated_hours), 0) as total_estimated_hours,
                COALESCE(SUM(actual_hours), 0) as total_actual_hours
            ")->first();

            return [
                'total' => (int) $row->total,
                'todo' => (int) $row->todo,
                'in_progress' => (int) $row->in_progress,
                'review' => (int) $row->review,
                'completed' => (int) $row->completed,
                'cancelled' => (int) $row->cancelled,
                'high_priority' => (int) $row->high_priority,
                'medium_priority' => (int) $row->medium_priority,
                'low_priority' => (int) $row->low_priority,
                'total_estimated_hours' => (float) $row->total_estimated_hours,
                'total_actual_hours' => (float) $row->total_actual_hours,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
