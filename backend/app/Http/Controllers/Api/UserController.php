<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\AuditLogService;

class UserController extends Controller
{
    /**
     * Display a listing of users (Admin / HR only)
     */
    public function index(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['admin', 'hr'])) {
            return response()->json(['success' => false, 'message' => 'Only Admin or HR can list users.'], 403);
        }
        $query = User::with(['roles', 'roles.permissions']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Store a newly created user (Admin only)
     */
    public function store(StoreUserRequest $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can add users.'], 403);
        }
        $userData = $request->validated();
        
        if (isset($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        }

        $userData['status'] = $userData['status'] ?? 'active';
        $userData['overtime_allowed'] = $userData['overtime_allowed'] ?? false;

        $roles = $userData['roles'] ?? [];
        unset($userData['roles']);
        $teamIds = $userData['team_ids'] ?? [];
        if (empty($teamIds) && !empty($userData['team_id'])) {
            $teamIds = [$userData['team_id']];
        }
        $projectManagerId = $userData['project_manager_id'] ?? null;
        unset($userData['team_id'], $userData['team_ids'], $userData['project_manager_id']);

        $user = User::create($userData);

        if (!empty($roles)) {
            $user->roles()->sync($roles);
        }

        $roleSlug = $user->roles->first()?->slug;
        foreach ($teamIds as $teamId) {
            $team = Team::find($teamId);
            if ($team) {
                $team->members()->attach($user->id, ['joined_at' => now(), 'status' => 'active']);
                if (in_array($roleSlug, ['team_lead', 'team_leader'])) {
                    $team->update(['team_lead_id' => $user->id]);
                    if ($projectManagerId) {
                        $team->update(['project_manager_id' => $projectManagerId]);
                    }
                }
                if (in_array($roleSlug, ['project_manager', 'manager'])) {
                    $team->update(['project_manager_id' => $user->id]);
                }
            }
        }

        $user->load('roles', 'teams');

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified user (Admin/HR or self)
     */
    public function show(User $user)
    {
        if ((int)$user->id !== (int)Auth::id() && !Auth::user()->hasAnyRole(['admin', 'hr'])) {
            return response()->json(['success' => false, 'message' => 'Only Admin or HR can view other users.'], 403);
        }
        $user->load('roles.permissions', 'teams');

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified user (Admin only)
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can update users.'], 403);
        }
        $oldData = $user->toArray();
        $userData = $request->validated();

        if (isset($userData['password']) && !empty($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        } else {
            unset($userData['password']);
        }

        $roles = $userData['roles'] ?? null;
        unset($userData['roles']);
        $teamIds = $userData['team_ids'] ?? null;
        if ($teamIds === null && isset($userData['team_id'])) {
            $teamIds = $userData['team_id'] ? [$userData['team_id']] : [];
        }
        $projectManagerId = $userData['project_manager_id'] ?? null;
        unset($userData['team_id'], $userData['team_ids'], $userData['project_manager_id']);

        $user->update($userData);

        if ($roles !== null) {
            $user->roles()->sync($roles);
        }

        if ($teamIds !== null) {
            $user->load('roles');
            $roleSlug = $user->roles->first()?->slug;
            $currentTeamIds = $user->teams()->pluck('teams.id')->all();
            // Detach from teams no longer in the list
            $toDetach = array_diff($currentTeamIds, $teamIds);
            foreach ($toDetach as $tid) {
                $team = Team::find($tid);
                if ($team) {
                    $team->members()->detach($user->id);
                    if ($team->team_lead_id === $user->id) {
                        $team->update(['team_lead_id' => null]);
                    }
                    if ($team->project_manager_id === $user->id) {
                        $team->update(['project_manager_id' => null]);
                    }
                }
            }
            foreach ($teamIds as $teamId) {
                $team = Team::find($teamId);
                if ($team) {
                    if (!$team->members()->where('user_id', $user->id)->exists()) {
                        $team->members()->attach($user->id, ['joined_at' => now(), 'status' => 'active']);
                    }
                    if (in_array($roleSlug, ['team_lead', 'team_leader'])) {
                        $team->update(['team_lead_id' => $user->id]);
                        if ($projectManagerId) {
                            $team->update(['project_manager_id' => $projectManagerId]);
                        }
                    }
                    if (in_array($roleSlug, ['project_manager', 'manager'])) {
                        $team->update(['project_manager_id' => $user->id]);
                    }
                }
            }
        }

        $user->load('roles', 'teams');

        // Log update
        AuditLogService::logUpdate('user', $user->id, $oldData, $user->toArray(), $request);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Remove the specified user (Admin only)
     */
    public function destroy(User $user)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can delete users.'], 403);
        }
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $userId = $user->id;
        $userData = $user->only(['id', 'name', 'email', 'employee_id', 'status', 'created_at']);
        $user->delete();

        try {
            AuditLogService::logDelete('user', $userId, $userData, $request);
        } catch (\Throwable $e) {
            \Log::warning('Audit log failed on user delete: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Assign roles to user
     */
    public function assignRoles(Request $request, User $user)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can assign roles.'], 403);
        }
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user->roles()->sync($request->roles);
        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'Roles assigned successfully',
            'data' => new UserResource($user),
        ]);
    }
}
