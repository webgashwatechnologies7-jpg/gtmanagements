<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EodReport;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportApprovalController extends Controller
{
    /**
     * Get pending approvals: Admin sees all company-wide; others see only those assigned to them
     */
    public function pending(Request $request)
    {
        $query = Approval::with(['approver', 'entity'])
            ->where('status', 'pending');

        if (!Auth::user()->hasRole('admin')) {
            $query->where('approver_id', Auth::id());
        }

        // Filter by entity type
        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        $approvals = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $approvals->items(),
            'meta' => [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'per_page' => $approvals->perPage(),
                'total' => $approvals->total(),
            ],
        ]);
    }

    /**
     * Approve EOD report
     */
    public function approve(Request $request, $reportId)
    {
        $request->validate([
            'comment' => 'nullable|string',
        ]);

        $eodReport = EodReport::findOrFail($reportId);

        // Check if user can approve this report
        if (!$this->canApprove($eodReport)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to approve this report',
            ], 403);
        }

        // Find pending approval (admin can act on any; others only their own)
        $approvalQuery = Approval::where('entity_type', 'eod_report')
            ->where('entity_id', $eodReport->id)
            ->where('status', 'pending');
        if (!Auth::user()->hasRole('admin')) {
            $approvalQuery->where('approver_id', Auth::id());
        }
        $approval = $approvalQuery->first();

        if (!$approval) {
            return response()->json([
                'success' => false,
                'message' => 'No pending approval found',
            ], 404);
        }

        // Update approval
        $approval->update([
            'status' => 'approved',
            'comment' => $request->comment,
        ]);

        // Check if this is the final approval
        $nextApprover = $this->getNextApprover($eodReport);
        
        if ($nextApprover) {
            // Create next approval
            $nextApproval = Approval::create([
                'entity_type' => 'eod_report',
                'entity_id' => $eodReport->id,
                'approver_id' => $nextApprover,
                'status' => 'pending',
            ]);

            // Notify next approver
            $nextApproverUser = \App\Models\User::find($nextApprover);
            if ($nextApproverUser) {
                \App\Services\NotificationService::notifyApprovalRequest(
                    $nextApproverUser,
                    'eod_report',
                    $eodReport->id,
                    "EOD Report for {$eodReport->date}"
                );
            }
        } else {
            // Final approval - mark report as approved
            $eodReport->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Notify employee about approval
            \App\Services\NotificationService::notifyApprovalStatus(
                $eodReport->user,
                'eod_report',
                $eodReport->id,
                'approved',
                "EOD Report for {$eodReport->date}"
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Report approved successfully',
            'data' => new \App\Http\Resources\EodReportResource($eodReport->load(['user', 'items.project', 'approver'])),
        ]);
    }

    /**
     * Reject EOD report
     */
    public function reject(Request $request, $reportId)
    {
        $request->validate([
            'comment' => 'required|string',
        ]);

        $eodReport = EodReport::findOrFail($reportId);

        // Check if user can reject this report
        if (!$this->canApprove($eodReport)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to reject this report',
            ], 403);
        }

        // Find pending approval (admin can act on any; others only their own)
        $approvalQuery = Approval::where('entity_type', 'eod_report')
            ->where('entity_id', $eodReport->id)
            ->where('status', 'pending');
        if (!Auth::user()->hasRole('admin')) {
            $approvalQuery->where('approver_id', Auth::id());
        }
        $approval = $approvalQuery->first();

        if (!$approval) {
            return response()->json([
                'success' => false,
                'message' => 'No pending approval found',
            ], 404);
        }

        // Update approval
        $approval->update([
            'status' => 'rejected',
            'comment' => $request->comment,
        ]);

        // Mark report as rejected
        $eodReport->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->comment,
        ]);

        // Notify employee about rejection
        \App\Services\NotificationService::notifyApprovalStatus(
            $eodReport->user,
            'eod_report',
            $eodReport->id,
            'rejected',
            "EOD Report for {$eodReport->date}"
        );

        // Clear dashboard cache
        \App\Helpers\CacheHelper::clearDashboardCache($eodReport->user_id);
        \App\Helpers\CacheHelper::clearDashboardCache(Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Report rejected',
            'data' => new \App\Http\Resources\EodReportResource($eodReport->load(['user', 'items.project', 'approver'])),
        ]);
    }

    /**
     * Check if user can approve this report (admin can approve any; others only if assigned to them)
     */
    private function canApprove(EodReport $eodReport)
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            return true;
        }
        return Approval::where('entity_type', 'eod_report')
            ->where('entity_id', $eodReport->id)
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Get next approver in the workflow
     */
    private function getNextApprover(EodReport $eodReport)
    {
        $user = $eodReport->user;
        
        // Approval workflow: Employee -> Team Lead -> Project Manager -> Admin
        
        // Get current approver role
        $currentApprover = Auth::user();
        
        if ($currentApprover->hasRole('team_lead')) {
            // Next: Project Manager
            $team = $user->teams()->first();
            if ($team && $team->project_manager_id) {
                return $team->project_manager_id;
            }
        } elseif ($currentApprover->hasRole('project_manager')) {
            // Next: Admin
            $admin = \App\Models\User::whereHas('roles', function ($q) {
                $q->where('slug', 'admin');
            })->first();
            return $admin ? $admin->id : null;
        }

        return null;
    }
}
