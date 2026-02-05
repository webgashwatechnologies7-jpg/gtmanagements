<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeEntry\StoreTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimeEntryController extends Controller
{
    /**
     * Display a listing of time entries
     */
    public function index(Request $request)
    {
        $query = TimeEntry::with(['user:id,name,email', 'project:id,name', 'approver:id,name']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        } else {
            // If not admin/PM/TL, only show own entries
            if (!Auth::user()->hasAnyRole(['admin', 'project_manager', 'team_lead'])) {
                $query->where('user_id', Auth::id());
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

        // Filter by project
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by entry type
        if ($request->has('entry_type')) {
            $query->where('entry_type', $request->entry_type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $timeEntries = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => TimeEntryResource::collection($timeEntries->items()),
            'meta' => [
                'current_page' => $timeEntries->currentPage(),
                'last_page' => $timeEntries->lastPage(),
                'per_page' => $timeEntries->perPage(),
                'total' => $timeEntries->total(),
            ],
        ]);
    }

    /**
     * Store a newly created time entry
     */
    public function store(StoreTimeEntryRequest $request)
    {
        $entryData = $request->validated();
        $entryData['user_id'] = Auth::id();
        $entryData['entry_type'] = $entryData['entry_type'] ?? 'regular';
        $entryData['status'] = 'pending';

        $timeEntry = TimeEntry::create($entryData);
        $timeEntry->load(['user', 'project']);

        return response()->json([
            'success' => true,
            'message' => 'Time entry created successfully',
            'data' => new TimeEntryResource($timeEntry),
        ], 201);
    }

    /**
     * Display the specified time entry
     */
    public function show(TimeEntry $timeEntry)
    {
        $timeEntry->load(['user', 'project', 'approver']);

        return response()->json([
            'success' => true,
            'data' => new TimeEntryResource($timeEntry),
        ]);
    }

    /**
     * Update the specified time entry
     */
    public function update(StoreTimeEntryRequest $request, TimeEntry $timeEntry)
    {
        // Check if user can update this entry
        if ($timeEntry->user_id !== Auth::id() && !Auth::user()->hasAnyRole(['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Can't update if already approved
        if ($timeEntry->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update approved time entry',
            ], 422);
        }

        $timeEntry->update($request->validated());
        $timeEntry->load(['user', 'project']);

        return response()->json([
            'success' => true,
            'message' => 'Time entry updated successfully',
            'data' => new TimeEntryResource($timeEntry),
        ]);
    }

    /**
     * Remove the specified time entry
     */
    public function destroy(TimeEntry $timeEntry)
    {
        if ($timeEntry->user_id !== Auth::id() && !Auth::user()->hasAnyRole(['admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $timeEntry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Time entry deleted successfully',
        ]);
    }

    /**
     * Approve time entry
     */
    public function approve(Request $request, TimeEntry $timeEntry)
    {
        $timeEntry->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $timeEntry->load(['user', 'project', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Time entry approved successfully',
            'data' => new TimeEntryResource($timeEntry),
        ]);
    }

    /**
     * Reject time entry
     */
    public function reject(Request $request, TimeEntry $timeEntry)
    {
        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $timeEntry->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        $timeEntry->load(['user', 'project', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Time entry rejected',
            'data' => new TimeEntryResource($timeEntry),
        ]);
    }
}
