<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    /**
     * Display a listing of roles (Admin only)
     */
    public function index(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can manage roles.'], 403);
        }
        $query = Role::with('permissions');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Get users count
        $query->withCount('users');

        $roles = $query->get();

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles),
        ]);
    }

    /**
     * Store a newly created role (Admin only)
     */
    public function store(StoreRoleRequest $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can manage roles.'], 403);
        }
        $roleData = $request->validated();
        $permissions = $roleData['permissions'] ?? [];
        unset($roleData['permissions']);
        $roleData['status'] = $roleData['status'] ?? 'active';

        $role = Role::create($roleData);

        if (!empty($permissions)) {
            $role->permissions()->sync($permissions);
        }

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * Display the specified role (Admin only)
     */
    public function show(Role $role)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can manage roles.'], 403);
        }
        $role->load('permissions');

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Update the specified role (Admin only)
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can manage roles.'], 403);
        }
        $roleData = $request->validated();
        $permissions = $roleData['permissions'] ?? null;
        unset($roleData['permissions']);

        $role->update($roleData);

        if ($permissions !== null) {
            $role->permissions()->sync($permissions);
        }

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Remove the specified role (Admin only)
     */
    public function destroy(Role $role)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can manage roles.'], 403);
        }
        // Prevent deleting default roles
        $defaultRoles = ['admin', 'project_manager', 'team_lead', 'employee'];
        if (in_array($role->slug, $defaultRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete default system roles.',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Assign permissions to role (Admin only)
     */
    public function assignPermissions(Request $request, Role $role)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can manage roles.'], 403);
        }
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($request->permissions);
        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned successfully',
            'data' => new RoleResource($role),
        ]);
    }
}
