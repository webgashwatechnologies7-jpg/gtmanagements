<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects
     */
    public function index(Request $request)
    {
        $query = Project::with([
            'projectType',
            'projectManager',
            'team',
            'creator',
            'latestTeamLeadAssignment.assignedTo',
        ]);
        $user = Auth::user();

        // Role-based visibility:
        // - Admin: all projects
        // - Sales: only projects they created
        // - PM: projects where project_manager_id = user
        // - TL: projects assigned to TL (project_assignments assignment_level=tl)
        // - Employee: projects assigned to employee (any assignment)
        if ($user->hasRole('sales')) {
            $query->where('created_by', $user->id);
        } elseif ($user->hasRole('project_manager')) {
            $query->where('project_manager_id', $user->id);
        } elseif ($user->hasRole('team_lead')) {
            $query->whereHas('assignments', function ($q) use ($user) {
                $q->where('assignment_level', 'tl')
                    ->where('status', 'active')
                    ->where('assigned_to_user_id', $user->id);
            });
        } elseif (!$user->hasRole('admin')) {
            $query->whereHas('assignments', function ($q) use ($user) {
                $q->where('status', 'active')
                    ->where('assigned_to_user_id', $user->id);
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by project type
        if ($request->has('project_type_id')) {
            $query->where('project_type_id', $request->project_type_id);
        }

        // Filter by project manager
        if ($request->has('project_manager_id')) {
            $query->where('project_manager_id', $request->project_manager_id);
        }

        // Filter by team
        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        // Filter by created_by (for Sales Team)
        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $projects = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProjectResource::collection($projects->items()),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    /**
     * Store a newly created project
     */
    public function store(StoreProjectRequest $request)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['admin', 'project_manager', 'sales'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $projectData = $request->validated();
        $projectData['created_by'] = Auth::id();
        $projectData['status'] = $projectData['status'] ?? 'planning';
        $projectData['priority'] = $projectData['priority'] ?? 'medium';

        $project = Project::create($projectData);
        $project->load(['projectType', 'projectManager', 'team', 'creator']);

        AuditLogService::logCreate('project', $project->id, $project->toArray(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully',
            'data' => new ProjectResource($project),
        ], 201);
    }

    /**
     * Display the specified project
     */
    public function show(Project $project)
    {
        $project->load([
            'projectType',
            'projectManager',
            'team',
            'creator',
            'assignedUsers',
            'latestTeamLeadAssignment.assignedTo',
        ]);
        $user = Auth::user();

        // Enforce same visibility rules as index
        // Sales: can only view projects they created
        if ($user->hasRole('sales') && (int) $project->created_by !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        if ($user->hasRole('project_manager') && (int) $project->project_manager_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        if ($user->hasRole('team_lead')) {
            $isAssigned = $project->assignments()
                ->where('assignment_level', 'tl')
                ->where('status', 'active')
                ->where('assigned_to_user_id', $user->id)
                ->exists();
            if (!$isAssigned) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }
        if (!$user->hasRole('admin') && !$user->hasRole('project_manager') && !$user->hasRole('team_lead') && !$user->hasRole('sales')) {
            $isAssigned = $project->assignments()
                ->where('status', 'active')
                ->where('assigned_to_user_id', $user->id)
                ->exists();
            if (!$isAssigned) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($project),
        ]);
    }

    /**
     * Update the specified project
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $user = Auth::user();
        $oldData = $project->toArray();

        // Sales: can update their own created projects (all fields)
        if ($user && $user->hasRole('sales')) {
            if ((int) $project->created_by !== (int) $user->id) {
                return response()->json(['success' => false, 'message' => 'You can only update projects you created'], 403);
            }
            $project->update($request->validated());
            AuditLogService::logUpdate('project', $project->id, $oldData, $project->fresh()->toArray(), $request);
            $project->load(['projectType', 'projectManager', 'team', 'creator']);
            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully',
                'data' => new ProjectResource($project),
            ]);
        }

        // Employee: can only change status, and only on their assigned projects
        if ($user && $user->hasRole('employee')) {
            $isAssigned = $project->assignments()
                ->where('status', 'active')
                ->where('assigned_to_user_id', $user->id)
                ->exists();
            if (!$isAssigned) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $data = $request->validate([
                'status' => 'required|in:planning,active,on_hold,completed,cancelled',
            ]);
            if (($data['status'] ?? null) === 'completed' && !$project->completion_date) {
                $data['completion_date'] = now()->toDateString();
            }
            $project->update($data);
        } elseif ($user && $user->hasAnyRole(['team_lead', 'team_leader'])) {
            // TL can only update status for projects assigned to them
            $isAssigned = $project->assignments()
                ->where('assignment_level', 'tl')
                ->where('status', 'active')
                ->where('assigned_to_user_id', $user->id)
                ->exists();
            if (!$isAssigned) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $data = $request->validate([
                'status' => 'required|in:planning,active,on_hold,completed,cancelled',
            ]);
            if (($data['status'] ?? null) === 'completed' && !$project->completion_date) {
                $data['completion_date'] = now()->toDateString();
            }
            $project->update($data);
        } else {
            // Admin / PM: normal validation
            $project->update($request->validated());
        }

        AuditLogService::logUpdate('project', $project->id, $oldData, $project->fresh()->toArray(), $request);
        $project->load(['projectType', 'projectManager', 'team', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully',
            'data' => new ProjectResource($project),
        ]);
    }

    /**
     * Remove the specified project
     */
    public function destroy(Project $project)
    {
        $user = Auth::user();
        
        // Sales can delete their own created projects
        if ($user && $user->hasRole('sales')) {
            if ((int) $project->created_by !== (int) $user->id) {
                return response()->json(['success' => false, 'message' => 'You can only delete projects you created'], 403);
            }
        } elseif (!$user || !$user->hasAnyRole(['admin', 'project_manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $projectData = $project->toArray();
        AuditLogService::logDelete('project', $project->id, $projectData, request());
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully',
        ]);
    }

    /**
     * Assign project to Project Manager
     */
    public function assignToPM(Request $request, Project $project)
    {
        $request->validate([
            'project_manager_id' => 'required|exists:users,id',
        ]);

        $project->update([
            'project_manager_id' => $request->project_manager_id,
        ]);

        // Create assignment record
        $project->assignments()->create([
            'assigned_to_user_id' => $request->project_manager_id,
            'assigned_by_user_id' => Auth::id(),
            'assignment_level' => 'pm',
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $assignedTo = User::find($request->project_manager_id);
        AuditLogService::log('assigned_pm', 'project', $project->id, null, [
            'assigned_to_user_id' => $request->project_manager_id,
            'assigned_to_name' => $assignedTo?->name,
            'assigned_by_user_id' => Auth::id(),
        ], $request);

        $project->load(['projectManager']);

        return response()->json([
            'success' => true,
            'message' => 'Project assigned to Project Manager successfully',
            'data' => new ProjectResource($project),
        ]);
    }

    /**
     * Assign project to Team Lead
     */
    public function assignToTL(Request $request, Project $project)
    {
        $user = Auth::user();
        
        // Sales can only assign their own created projects
        if ($user->hasRole('sales')) {
            if ((int) $project->created_by !== (int) $user->id) {
                return response()->json(['success' => false, 'message' => 'You can only assign projects you created'], 403);
            }
        } elseif (!$user->hasAnyRole(['admin', 'project_manager'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'team_lead_id' => 'required|exists:users,id',
        ]);

        // Create assignment record
        $project->assignments()->create([
            'assigned_to_user_id' => $request->team_lead_id,
            'assigned_by_user_id' => Auth::id(),
            'assignment_level' => 'tl',
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $assignedTo = User::find($request->team_lead_id);
        AuditLogService::log('assigned_tl', 'project', $project->id, null, [
            'assigned_to_user_id' => $request->team_lead_id,
            'assigned_to_name' => $assignedTo?->name,
            'assigned_by_user_id' => Auth::id(),
        ], $request);

        $project->load(['assignedUsers']);

        return response()->json([
            'success' => true,
            'message' => 'Project assigned to Team Lead successfully',
            'data' => new ProjectResource($project),
        ]);
    }

    /**
     * Assign project to Employee
     */
    public function assignToEmployee(Request $request, Project $project)
    {
        $authUser = Auth::user();
        if (!$authUser->hasAnyRole(['admin', 'project_manager', 'team_lead', 'team_leader'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'employee_id' => 'required|exists:users,id',
        ]);

        // If TL is assigning, restrict to:
        // - TL must be assigned to this project
        // - employee must be in TL's team
        if ($authUser->hasRole('team_lead')) {
            $isAssignedToTl = $project->assignments()
                ->where('assignment_level', 'tl')
                ->where('status', 'active')
                ->where('assigned_to_user_id', $authUser->id)
                ->exists();
            if (!$isAssignedToTl) {
                return response()->json(['success' => false, 'message' => 'You can only assign members on your assigned projects.'], 403);
            }

            $team = \App\Models\Team::where('team_lead_id', $authUser->id)->first();
            if (!$team) {
                return response()->json(['success' => false, 'message' => 'No team found for this Team Lead.'], 422);
            }

            $isMember = $team->members()
                ->where('users.id', $request->employee_id)
                ->exists();
            if (!$isMember) {
                return response()->json(['success' => false, 'message' => 'You can only assign users from your own team.'], 403);
            }
        }

        // Ek employee ko multiple projects assign ho sakte hain; lekin same project pe duplicate na ho
        $alreadyAssigned = $project->assignments()
            ->where('assigned_to_user_id', $request->employee_id)
            ->where('assignment_level', 'employee')
            ->where('status', 'active')
            ->exists();
        if ($alreadyAssigned) {
            return response()->json(['success' => false, 'message' => 'This employee is already assigned to this project.'], 422);
        }

        // Create assignment record
        $project->assignments()->create([
            'assigned_to_user_id' => $request->employee_id,
            'assigned_by_user_id' => Auth::id(),
            'assignment_level' => 'employee',
            'assigned_at' => now(),
            'status' => 'active',
        ]);

        $employee = \App\Models\User::find($request->employee_id);
        AuditLogService::log('assigned_employee', 'project', $project->id, null, [
            'assigned_to_user_id' => $request->employee_id,
            'assigned_to_name' => $employee?->name,
            'assigned_by_user_id' => Auth::id(),
        ], $request);

        if ($employee) {
            \App\Services\NotificationService::notifyProjectAssignment(
                $employee,
                $project->id,
                $project->name
            );
        }

        // Clear dashboard cache for employee
        \App\Helpers\CacheHelper::clearDashboardCache($request->employee_id, 'employee');

        $project->load(['assignedUsers']);

        return response()->json([
            'success' => true,
            'message' => 'Project assigned to Employee successfully',
            'data' => new ProjectResource($project),
        ]);
    }

    /**
     * Project history: who created, assigned, changed status, deleted (audit log – records are not deleted)
     */
    public function history(Request $request, Project $project)
    {
        $project->load([
            'projectType',
            'projectManager',
            'team',
            'creator',
        ]);
        $user = Auth::user();

        // Same visibility as show(): whoever can view the project can also view history
        // Sales: can only view history of projects they created
        if ($user->hasRole('sales') && (int) $project->created_by !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        if ($user->hasRole('project_manager') && (int) $project->project_manager_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        if ($user->hasRole('team_lead')) {
            $isAssigned = $project->assignments()
                ->where('assignment_level', 'tl')
                ->where('status', 'active')
                ->where('assigned_to_user_id', $user->id)
                ->exists();
            if (!$isAssigned) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }
        if (!$user->hasRole('admin') && !$user->hasRole('project_manager') && !$user->hasRole('team_lead') && !$user->hasRole('sales')) {
            $isAssigned = $project->assignments()
                ->where('status', 'active')
                ->where('assigned_to_user_id', $user->id)
                ->exists();
            if (!$isAssigned) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        $query = \App\Models\AuditLog::with('user')
            ->where('entity_type', 'project')
            ->where('entity_id', $project->id)
            ->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\AuditLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
