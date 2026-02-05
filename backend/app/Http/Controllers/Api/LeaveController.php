<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveRequest;
use App\Http\Resources\LeaveResource;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    /**
     * List leaves: own for employee; all for Admin/HR
     */
    public function index(Request $request)
    {
        $query = Leave::with(['user:id,name,email', 'approver:id,name']);

        if (! Auth::user()->hasAnyRole(['admin', 'hr'])) {
            $query->where('user_id', Auth::id());
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('user_id') && Auth::user()->hasAnyRole(['admin', 'hr'])) {
            $query->where('user_id', $request->user_id);
        }

        $perPage = $request->get('per_page', 15);
        $leaves = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => LeaveResource::collection($leaves->items()),
            'meta' => [
                'current_page' => $leaves->currentPage(),
                'last_page' => $leaves->lastPage(),
                'per_page' => $leaves->perPage(),
                'total' => $leaves->total(),
            ],
        ]);
    }

    /**
     * Apply for leave (any role)
     */
    public function store(StoreLeaveRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['status'] = 'pending';

        $leave = Leave::create($data);
        $leave->load(['user', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Leave application submitted. Admin/HR will approve.',
            'data' => new LeaveResource($leave),
        ], 201);
    }

    /**
     * Show single leave
     */
    public function show(Leave $leave)
    {
        if ($leave->user_id !== Auth::id() && ! Auth::user()->hasAnyRole(['admin', 'hr'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        $leave->load(['user', 'approver']);
        return response()->json([
            'success' => true,
            'data' => new LeaveResource($leave),
        ]);
    }

    /**
     * Approve leave (Admin / HR only)
     */
    public function approve(Request $request, Leave $leave)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin or HR can approve leave.',
            ], 403);
        }

        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This leave is already processed.',
            ], 422);
        }

        $leave->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
        $leave->load(['user', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Leave approved.',
            'data' => new LeaveResource($leave),
        ]);
    }

    /**
     * Reject leave (Admin / HR only)
     */
    public function reject(Request $request, Leave $leave)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin or HR can reject leave.',
            ], 403);
        }

        if ($leave->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This leave is already processed.',
            ], 422);
        }

        $leave->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->input('reason'),
        ]);
        $leave->load(['user', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Leave rejected.',
            'data' => new LeaveResource($leave),
        ]);
    }

    /**
     * Dashboard counts for Admin/HR: pending, approved, rejected
     */
    public function dashboard(Request $request)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'hr'])) {
            return response()->json([
                'success' => true,
                'data' => [
                    'pending' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'items' => [],
                ],
            ]);
        }

        $pending = Leave::with(['user:id,name,email'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $counts = [
            'pending' => Leave::where('status', 'pending')->count(),
            'approved' => Leave::where('status', 'approved')->count(),
            'rejected' => Leave::where('status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'pending_count' => $counts['pending'],
                'approved_count' => $counts['approved'],
                'rejected_count' => $counts['rejected'],
                'pending_items' => LeaveResource::collection($pending),
            ],
        ]);
    }
}
