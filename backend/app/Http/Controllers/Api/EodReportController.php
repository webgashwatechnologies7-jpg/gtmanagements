<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EodReport\StoreEodReportRequest;
use App\Http\Resources\EodReportResource;
use App\Models\EodReport;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EodReportController extends Controller
{
    /**
     * Display a listing of EOD reports
     */
    public function index(Request $request)
    {
        $query = EodReport::with(['user:id,name,email', 'user.teams:id,name', 'items.project:id,name', 'approver:id,name']);

        // Filter by user (explicit)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        } else {
            $user = Auth::user();
            if ($user->hasAnyRole(['team_lead', 'team_leader'])) {
                // TL default view = own EODs
                $query->where('user_id', $user->id);
            } elseif (!$user->hasAnyRole(['admin', 'project_manager'])) {
                // Employee: only own reports
                $query->where('user_id', $user->id);
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
        $reports = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => EodReportResource::collection($reports->items()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * TL: List team members EOD reports (excluding TL).
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
                $q->where('users.id', $user->id)
                    ->where('team_members.status', 'active');
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

        $query = EodReport::with(['user:id,name,email', 'items.project:id,name', 'approver:id,name'])
            ->whereIn('user_id', $teamMemberIds);

        if ($request->has('date')) {
            $query->where('date', $request->date);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $reports = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => EodReportResource::collection($reports->items()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Store a newly created EOD report (only today's date – not past or future)
     */
    public function store(StoreEodReportRequest $request)
    {
        $reportData = $request->validated();
        $today = now()->format('Y-m-d');
        if (($reportData['date'] ?? '') !== $today) {
            return response()->json([
                'success' => false,
                'message' => 'EOD report can only be created for today\'s date. Past or future dates are not allowed.',
            ], 422);
        }
        $reportData['user_id'] = Auth::id();
        $reportData['status'] = 'draft';

        $items = $reportData['items'] ?? [];
        unset($reportData['items']);

        $eodReport = EodReport::create($reportData);

        // Create report items
        foreach ($items as $item) {
            $eodReport->items()->create($item);
        }

        $eodReport->load(['user', 'items.project']);

        return response()->json([
            'success' => true,
            'message' => 'EOD report created successfully',
            'data' => new EodReportResource($eodReport),
        ], 201);
    }

    /**
     * Display the specified EOD report
     */
    public function show(EodReport $eodReport)
    {
        // Check if user can view this report
        $user = Auth::user();
        if ($user->hasAnyRole(['team_lead', 'team_leader'])) {
            $team = \App\Models\Team::where('team_lead_id', $user->id)->first();
            $isMember = $team
                ? $team->members()->where('users.id', $eodReport->user_id)->exists()
                : ((int) $eodReport->user_id === (int) $user->id);
            if (!$isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        } elseif ($eodReport->user_id !== $user->id && !$user->hasAnyRole(['admin', 'project_manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $eodReport->load(['user', 'items.project', 'approver', 'approvals.approver']);

        return response()->json([
            'success' => true,
            'data' => new EodReportResource($eodReport),
        ]);
    }

    /**
     * Update the specified EOD report
     */
    public function update(StoreEodReportRequest $request, EodReport $eodReport)
    {
        // Check if user can update this report
        if ($eodReport->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Sirf draft ya rejected report edit ho sakti hai; submitted/approved par edit nahi
        if (! in_array($eodReport->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft or rejected reports can be edited. Submitted/approved reports are view-only.',
            ], 422);
        }

        $reportData = $request->validated();
        $items = $reportData['items'] ?? [];
        unset($reportData['items']);

        $eodReport->update($reportData);

        // Update items (delete old and create new)
        $eodReport->items()->delete();
        foreach ($items as $item) {
            $eodReport->items()->create($item);
        }

        $eodReport->load(['user', 'items.project']);

        return response()->json([
            'success' => true,
            'message' => 'EOD report updated successfully',
            'data' => new EodReportResource($eodReport),
        ]);
    }

    /**
     * Submit EOD report
     */
    public function submit(EodReport $eodReport)
    {
        if ($eodReport->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($eodReport->status === 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Report already submitted',
            ], 422);
        }

        $eodReport->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Create approval record for Team Lead
        // Approval workflow: Employee -> Team Lead -> Project Manager -> Admin
        $nextApproverId = $this->getNextApprover($eodReport);
        
        if ($nextApproverId) {
            Approval::create([
                'entity_type' => 'eod_report',
                'entity_id' => $eodReport->id,
                'approver_id' => $nextApproverId,
                'status' => 'pending',
            ]);

            // Notify approver
            $approver = \App\Models\User::find($nextApproverId);
            if ($approver) {
                \App\Services\NotificationService::notifyApprovalRequest(
                    $approver,
                    'eod_report',
                    $eodReport->id,
                    "EOD Report for {$eodReport->date->format('Y-m-d')}"
                );
            }
        }

        // Notify employee about submission
        \App\Services\NotificationService::notifyReportSubmission(
            $eodReport->user,
            'eod',
            $eodReport->id,
            $eodReport->date->format('Y-m-d')
        );

        return response()->json([
            'success' => true,
            'message' => 'EOD report submitted successfully',
            'data' => new EodReportResource($eodReport->load(['user', 'items.project'])),
        ]);
    }

    /**
     * Remove the specified EOD report
     */
    public function destroy(EodReport $eodReport)
    {
        if ($eodReport->user_id !== Auth::id() && ! Auth::user()->hasAnyRole(['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Owner can only delete draft/rejected; submitted/approved cannot be deleted
        if ($eodReport->user_id === Auth::id() && ! in_array($eodReport->status, ['draft', 'rejected'])) {
            return response()->json([
                'success' => false,
                'message' => 'Submitted or approved reports cannot be deleted.',
            ], 422);
        }

        $eodReport->delete();

        return response()->json([
            'success' => true,
            'message' => 'EOD report deleted successfully',
        ]);
    }

    /**
     * Get next approver in the workflow
     */
    private function getNextApprover(EodReport $eodReport)
    {
        $user = $eodReport->user;
        
        // Get user's team lead
        $team = $user->teams()->first();
        if ($team && $team->team_lead_id) {
            return $team->team_lead_id;
        }

        // If no team lead, get project manager
        // This logic can be enhanced based on project assignments
        return null; // Will be handled by approval system
    }
}
