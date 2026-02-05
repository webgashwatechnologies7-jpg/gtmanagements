<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BulkOperationController extends Controller
{
    /**
     * Bulk update user status
     */
    public function bulkUpdateUserStatus(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'status' => 'required|in:active,inactive',
        ]);

        $updated = User::whereIn('id', $request->user_ids)
            ->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => "Updated {$updated} users",
            'data' => ['updated_count' => $updated],
        ]);
    }

    /**
     * Bulk assign users to team
     */
    public function bulkAssignToTeam(Request $request, $teamId)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $team = Team::findOrFail($teamId);

        DB::transaction(function () use ($team, $request) {
            foreach ($request->user_ids as $userId) {
                $team->members()->syncWithoutDetaching([$userId]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Users assigned to team successfully',
            'data' => ['assigned_count' => count($request->user_ids)],
        ]);
    }

    /**
     * Bulk assign projects to users
     */
    public function bulkAssignProjects(Request $request)
    {
        $request->validate([
            'project_ids' => 'required|array',
            'project_ids.*' => 'exists:projects,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $assigned = 0;

        DB::transaction(function () use ($request, &$assigned) {
            foreach ($request->project_ids as $projectId) {
                $project = Project::find($projectId);
                foreach ($request->user_ids as $userId) {
                    $project->assignments()->firstOrCreate([
                        'assigned_to_user_id' => $userId,
                        'assigned_by_user_id' => Auth::id(),
                        'assignment_level' => 'employee',
                        'status' => 'active',
                    ]);
                    $assigned++;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Assigned {$assigned} project-user combinations",
            'data' => ['assigned_count' => $assigned],
        ]);
    }

    /**
     * Bulk delete users
     */
    public function bulkDeleteUsers(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Prevent deleting yourself
        $userIds = array_diff($request->user_ids, [Auth::id()]);

        if (empty($userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account',
            ], 422);
        }

        $deleted = User::whereIn('id', $userIds)->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} users",
            'data' => ['deleted_count' => $deleted],
        ]);
    }
}
