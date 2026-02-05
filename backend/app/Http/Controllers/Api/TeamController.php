<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Models\ProjectAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    /**
     * Display a listing of teams (Admin / PM only)
     */
    public function index(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['admin', 'project_manager'])) {
            return response()->json(['success' => false, 'message' => 'Only Admin or Project Manager can list teams.'], 403);
        }
        $query = Team::with(['teamLead', 'projectManager']);

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

        // Filter by project manager
        if ($request->has('project_manager_id')) {
            $query->where('project_manager_id', $request->project_manager_id);
        }

        // Filter by team lead
        if ($request->has('team_lead_id')) {
            $query->where('team_lead_id', $request->team_lead_id);
        }

        // Get members count
        $query->withCount('activeMembers');

        // Pagination
        $perPage = $request->get('per_page', 15);
        $teams = $query->paginate($perPage);

        // Fallback: for teams with no team_lead_id, use a member who has Team Lead role (so dropdown shows e.g. Neha)
        $teamItems = $teams->items();
        $nullTlTeamIds = collect($teamItems)->whereNull('team_lead_id')->pluck('id')->all();
        if (!empty($nullTlTeamIds)) {
            $tlUsers = User::whereHas('teams', fn ($q) => $q->whereIn('teams.id', $nullTlTeamIds))
                ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['team_lead', 'team_leader']))
                ->with(['teams' => fn ($q) => $q->whereIn('teams.id', $nullTlTeamIds)])
                ->get();
            $teamIdToTl = [];
            foreach ($tlUsers as $user) {
                foreach ($user->teams as $t) {
                    if (!isset($teamIdToTl[$t->id])) {
                        $teamIdToTl[$t->id] = $user;
                    }
                }
            }
            foreach ($teamItems as $team) {
                if ($team->team_lead_id === null && isset($teamIdToTl[$team->id])) {
                    $team->setRelation('teamLead', $teamIdToTl[$team->id]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => TeamResource::collection($teamItems),
            'meta' => [
                'current_page' => $teams->currentPage(),
                'last_page' => $teams->lastPage(),
                'per_page' => $teams->perPage(),
                'total' => $teams->total(),
            ],
        ]);
    }

    /**
     * Get current Team Lead's team members (TL only).
     */
    public function myMembers(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['team_lead', 'team_leader'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Primary: team_lead_id is set
        $team = Team::where('team_lead_id', $user->id)->first();
        // Fallback: team_lead_id not set but TL is a member of the team
        if (!$team) {
            $team = Team::whereHas('members', function ($q) use ($user) {
                $q->where('users.id', $user->id)
                    ->where('team_members.status', 'active');
            })->first();
        }

        if (!$team) {
            return response()->json([
                'success' => true,
                'data' => [
                    'team' => null,
                    'members' => [],
                ],
            ]);
        }

        $members = $team->members()
            ->select(['users.id', 'users.name', 'users.email', 'users.employee_id', 'users.phone', 'users.status'])
            ->wherePivot('status', 'active')
            ->where('users.id', '!=', $user->id)
            // TL should see employees under them (not PM/TL accounts)
            ->whereHas('roles', function ($q) {
                $q->where('slug', 'employee');
            })
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'members' => $members,
            ],
        ]);
    }

    /**
     * TL: Get a specific team member details + assigned projects history.
     */
    public function myMemberDetail(Request $request, User $user)
    {
        $authUser = $request->user();
        if (!$authUser || !$authUser->hasAnyRole(['team_lead', 'team_leader'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $team = Team::where('team_lead_id', $authUser->id)->first();
        if (!$team) {
            $team = Team::whereHas('members', function ($q) use ($authUser) {
                $q->where('users.id', $authUser->id)
                    ->where('team_members.status', 'active');
            })->first();
        }
        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'No team found for this Team Lead.',
            ], 422);
        }

        $isMember = $team->members()->wherePivot('status', 'active')->where('users.id', $user->id)->exists();
        if (!$isMember) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Only employees (not TL/PM/admin accounts)
        $isEmployee = $user->roles()->where('slug', 'employee')->exists();
        if (!$isEmployee) {
            return response()->json([
                'success' => false,
                'message' => 'This user is not an employee.',
            ], 422);
        }

        $assignments = ProjectAssignment::query()
            ->where('assignment_level', 'employee')
            ->where('assigned_to_user_id', $user->id)
            ->with([
                'project:id,name,status,deadline,completion_date',
                'assignedBy:id,name,email',
            ])
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($a) use ($authUser) {
                return [
                    'project' => $a->project ? [
                        'id' => $a->project->id,
                        'name' => $a->project->name,
                        'status' => $a->project->status,
                        'deadline' => $a->project->deadline?->format('Y-m-d'),
                        'completion_date' => $a->project->completion_date?->format('Y-m-d'),
                    ] : null,
                    'assigned_at' => $a->assigned_at?->format('Y-m-d H:i:s'),
                    'assigned_by' => $a->assignedBy ? [
                        'id' => $a->assignedBy->id,
                        'name' => $a->assignedBy->name,
                        'email' => $a->assignedBy->email,
                    ] : null,
                    'assigned_by_me' => (int) $a->assigned_by_user_id === (int) $authUser->id,
                ];
            })
            ->filter(fn ($row) => !empty($row['project']))
            ->values();

        $totalAssigned = $assignments->count();
        $completedCount = $assignments->filter(fn ($row) => ($row['project']['status'] ?? null) === 'completed')->count();
        $assignedByMeCount = $assignments->filter(fn ($row) => $row['assigned_by_me'])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
                'employee' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
                ],
                'stats' => [
                    'total_assigned' => $totalAssigned,
                    'assigned_by_me' => $assignedByMeCount,
                    'completed' => $completedCount,
                ],
                'assignments' => $assignments,
            ],
        ]);
    }

    /**
     * Store a newly created team (Admin / PM only)
     */
    public function store(StoreTeamRequest $request)
    {
        if (!Auth::user()->hasAnyRole(['admin', 'project_manager'])) {
            return response()->json(['success' => false, 'message' => 'Only Admin or Project Manager can create teams.'], 403);
        }
        $teamData = $request->validated();
        $members = $teamData['members'] ?? [];
        unset($teamData['members']);

        $teamData['status'] = $teamData['status'] ?? 'active';

        $team = Team::create($teamData);

        // Add team members
        if (!empty($members)) {
            $membersData = [];
            foreach ($members as $userId) {
                $membersData[$userId] = [
                    'joined_at' => now(),
                    'status' => 'active',
                ];
            }
            $team->members()->sync($membersData);
        }

        $team->load(['teamLead', 'projectManager', 'members']);

        return response()->json([
            'success' => true,
            'message' => 'Team created successfully',
            'data' => new TeamResource($team),
        ], 201);
    }

    /**
     * Display the specified team (Admin / PM / TL of that team)
     */
    public function show(Team $team)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && !$user->hasRole('project_manager')) {
            $isTL = $team->team_lead_id === $user->id || $team->members()->where('users.id', $user->id)->wherePivot('status', 'active')->exists();
            if (!$isTL) {
                return response()->json(['success' => false, 'message' => 'Only Admin, PM or Team Lead can view this team.'], 403);
            }
        }
        $team->load(['teamLead', 'projectManager', 'members.roles']);

        return response()->json([
            'success' => true,
            'data' => new TeamResource($team),
        ]);
    }

    /**
     * Update the specified team (Admin / PM only)
     */
    public function update(UpdateTeamRequest $request, Team $team)
    {
        if (!Auth::user()->hasAnyRole(['admin', 'project_manager'])) {
            return response()->json(['success' => false, 'message' => 'Only Admin or Project Manager can update teams.'], 403);
        }
        $teamData = $request->validated();
        $members = $teamData['members'] ?? null;
        unset($teamData['members']);

        $team->update($teamData);

        // Update team members
        if ($members !== null) {
            $membersData = [];
            foreach ($members as $userId) {
                $membersData[$userId] = [
                    'joined_at' => now(),
                    'status' => 'active',
                ];
            }
            $team->members()->sync($membersData);
        }

        $team->load(['teamLead', 'projectManager', 'members']);

        return response()->json([
            'success' => true,
            'message' => 'Team updated successfully',
            'data' => new TeamResource($team),
        ]);
    }

    /**
     * Remove the specified team
     */
    public function destroy(Team $team)
    {
        if (!Auth::user()->hasAnyRole(['admin', 'project_manager'])) {
            return response()->json(['success' => false, 'message' => 'Only Admin or Project Manager can delete teams.'], 403);
        }
        $team->delete();

        return response()->json([
            'success' => true,
            'message' => 'Team deleted successfully',
        ]);
    }

    /**
     * Get team statistics
     */
    public function statistics(Team $team)
    {
        $team->load(['members', 'teamLead', 'projectManager']);

        return response()->json([
            'success' => true,
            'data' => [
                'team' => new TeamResource($team),
                'statistics' => [
                    'total_members' => $team->activeMembers()->count(),
                    'active_members' => $team->activeMembers()->count(),
                    'inactive_members' => $team->members()->wherePivot('status', 'inactive')->count(),
                ],
            ],
        ]);
    }

    /**
     * Assign members to team
     */
    public function assignMembers(Request $request, Team $team)
    {
        if (!Auth::user()->hasAnyRole(['admin', 'project_manager'])) {
            return response()->json(['success' => false, 'message' => 'Only Admin or Project Manager can assign team members.'], 403);
        }
        $request->validate([
            'members' => 'required|array',
            'members.*' => 'exists:users,id',
        ]);

        $membersData = [];
        foreach ($request->members as $userId) {
            $membersData[$userId] = [
                'joined_at' => now(),
                'status' => 'active',
            ];
        }

        $team->members()->sync($membersData);
        $team->load('members');

        return response()->json([
            'success' => true,
            'message' => 'Members assigned successfully',
            'data' => new TeamResource($team),
        ]);
    }

    /**
     * Remove member from team
     */
    public function removeMember(Team $team, $userId)
    {
        if (!Auth::user()->hasAnyRole(['admin', 'project_manager'])) {
            return response()->json(['success' => false, 'message' => 'Only Admin or Project Manager can remove team members.'], 403);
        }
        $team->members()->detach($userId);

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully',
        ]);
    }
}
