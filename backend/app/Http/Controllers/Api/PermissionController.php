<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions
     */
    public function index(Request $request)
    {
        $query = Permission::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%");
            });
        }

        // Filter by module
        if ($request->has('module')) {
            $query->where('module', $request->module);
        }

        $permissions = $query->get();

        return response()->json([
            'success' => true,
            'data' => PermissionResource::collection($permissions),
        ]);
    }

    /**
     * Display the specified permission
     */
    public function show(Permission $permission)
    {
        return response()->json([
            'success' => true,
            'data' => new PermissionResource($permission),
        ]);
    }
}
