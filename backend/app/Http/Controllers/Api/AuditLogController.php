<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs
     */
    public function index(Request $request)
    {
        // Only admin can view audit logs
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can view audit logs.',
            ], 403);
        }

        $query = AuditLog::with('user');

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by entity
        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
            if ($request->has('entity_id')) {
                $query->where('entity_id', $request->entity_id);
            }
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Pagination
        $perPage = $request->get('per_page', 50);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AuditLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Display the specified audit log
     */
    public function show(AuditLog $auditLog)
    {
        // Only admin can view audit logs
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can view audit logs.',
            ], 403);
        }

        $auditLog->load('user');

        return response()->json([
            'success' => true,
            'data' => new AuditLogResource($auditLog),
        ]);
    }
}
