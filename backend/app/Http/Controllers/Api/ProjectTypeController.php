<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectType\StoreProjectTypeRequest;
use App\Http\Requests\ProjectType\UpdateProjectTypeRequest;
use App\Http\Resources\ProjectTypeResource;
use App\Models\ProjectType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectTypeController extends Controller
{
    /**
     * Display a listing of project types
     */
    public function index(Request $request)
    {
        $query = ProjectType::query();

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Get projects count
        $query->withCount('projects');

        // Pagination
        $perPage = $request->get('per_page', 15);
        $projectTypes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProjectTypeResource::collection($projectTypes->items()),
            'meta' => [
                'current_page' => $projectTypes->currentPage(),
                'last_page' => $projectTypes->lastPage(),
                'per_page' => $projectTypes->perPage(),
                'total' => $projectTypes->total(),
            ],
        ]);
    }

    /**
     * Store a newly created project type (Admin only)
     */
    public function store(StoreProjectTypeRequest $request)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can create project types.'], 403);
        }
        $projectType = ProjectType::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Project type created successfully',
            'data' => new ProjectTypeResource($projectType),
        ], 201);
    }

    /**
     * Display the specified project type
     */
    public function show(ProjectType $projectType)
    {
        $projectType->loadCount('projects');

        return response()->json([
            'success' => true,
            'data' => new ProjectTypeResource($projectType),
        ]);
    }

    /**
     * Update the specified project type (Admin only)
     */
    public function update(UpdateProjectTypeRequest $request, ProjectType $projectType)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can update project types.'], 403);
        }
        $projectType->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Project type updated successfully',
            'data' => new ProjectTypeResource($projectType),
        ]);
    }

    /**
     * Remove the specified project type (Admin only)
     */
    public function destroy(ProjectType $projectType)
    {
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Only Admin can delete project types.'], 403);
        }
        // Check if project type has projects
        if ($projectType->projects()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete project type with existing projects',
            ], 422);
        }

        $projectType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project type deleted successfully',
        ]);
    }
}
