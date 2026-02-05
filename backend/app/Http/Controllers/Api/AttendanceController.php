<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\DailyPlan;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records
     */
    public function index(Request $request)
    {
        $query = Attendance::with(['user:id,name,email', 'markedBy:id,name']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        } else {
            // If not admin/PM/TL, only show own attendance
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

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $attendances = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AttendanceResource::collection($attendances->items()),
            'meta' => [
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
            ],
        ]);
    }

    /**
     * Store a newly created attendance record (manual marking – Admin / HR only)
     */
    public function store(StoreAttendanceRequest $request)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin or HR can add attendance manually.',
            ], 403);
        }

        $attendanceData = $request->validated();
        $attendanceData['marked_by'] = Auth::id();

        // Calculate work minutes if check-in and check-out provided
        if (isset($attendanceData['check_in_time']) && isset($attendanceData['check_out_time'])) {
            $checkIn = Carbon::parse($attendanceData['check_in_time']);
            $checkOut = Carbon::parse($attendanceData['check_out_time']);
            $breakMinutes = $attendanceData['break_minutes'] ?? 60;
            
            $totalMinutes = $checkOut->diffInMinutes($checkIn) - $breakMinutes;
            $attendanceData['total_work_minutes'] = max(0, $totalMinutes);
            
            // Calculate regular (max 480 = 8 hours) and overtime
            $attendanceData['regular_minutes'] = min(480, $attendanceData['total_work_minutes']);
            $attendanceData['overtime_minutes'] = max(0, $attendanceData['total_work_minutes'] - 480);
        }

        $attendance = Attendance::create($attendanceData);
        $attendance->load(['user', 'markedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Attendance marked successfully',
            'data' => new AttendanceResource($attendance),
        ], 201);
    }

    /**
     * Display the specified attendance record
     */
    public function show(Attendance $attendance)
    {
        $attendance->load(['user', 'markedBy']);

        return response()->json([
            'success' => true,
            'data' => new AttendanceResource($attendance),
        ]);
    }

    /**
     * Update the specified attendance record (Admin / HR only)
     */
    public function update(StoreAttendanceRequest $request, Attendance $attendance)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin or HR can edit attendance.',
            ], 403);
        }

        $attendanceData = $request->validated();

        // Recalculate if times changed
        if (isset($attendanceData['check_in_time']) && isset($attendanceData['check_out_time'])) {
            $checkIn = Carbon::parse($attendanceData['check_in_time']);
            $checkOut = Carbon::parse($attendanceData['check_out_time']);
            $breakMinutes = $attendanceData['break_minutes'] ?? $attendance->break_minutes;
            
            $totalMinutes = $checkOut->diffInMinutes($checkIn) - $breakMinutes;
            $attendanceData['total_work_minutes'] = max(0, $totalMinutes);
            
            $attendanceData['regular_minutes'] = min(480, $attendanceData['total_work_minutes']);
            $attendanceData['overtime_minutes'] = max(0, $attendanceData['total_work_minutes'] - 480);
        }

        $attendance->update($attendanceData);
        $attendance->load(['user', 'markedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Attendance updated successfully',
            'data' => new AttendanceResource($attendance),
        ]);
    }

    /**
     * Remove the specified attendance record (sirf Admin / HR)
     */
    public function destroy(Attendance $attendance)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Admin or HR can delete attendance.',
            ], 403);
        }

        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance deleted successfully',
        ]);
    }

    /**
     * Get today's attendance for current user (for dashboard check-in/check-out UI)
     */
    public function today(Request $request)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $userId = $request->get('user_id', Auth::id());

        // Non-admin can only see own today
        if ((int) $userId !== (int) Auth::id() && ! Auth::user()->hasAnyRole(['admin', 'project_manager', 'team_lead'])) {
            $userId = Auth::id();
        }

        $attendance = Attendance::with(['user:id,name,email'])
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $attendance ? new AttendanceResource($attendance) : null,
        ]);
    }

    /**
     * Self check-in: create today's attendance with check_in_time = now()
     */
    public function checkIn(Request $request)
    {
        $user = Auth::user();
        $date = Carbon::today()->format('Y-m-d');

        $existing = Attendance::where('user_id', $user->id)->where('date', $date)->first();

        if ($existing) {
            if ($existing->check_in_time) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already checked in today.',
                ], 422);
            }
            $existing->update([
                'check_in_time' => now(),
                'status' => 'present',
            ]);
            $existing->load(['user', 'markedBy']);
            return response()->json([
                'success' => true,
                'message' => 'Check-in recorded.',
                'data' => new AttendanceResource($existing),
            ]);
        }

        // Holiday check (optional: still allow check-in and mark present)
        $holiday = Holiday::where('date', $date)->where('is_active', true)->first();
        $status = $holiday ? 'holiday' : 'present';

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'status' => $status,
            'check_in_time' => now(),
            'break_minutes' => 0,
            'marked_by' => $user->id,
        ]);
        $attendance->load(['user', 'markedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Check-in recorded.',
            'data' => new AttendanceResource($attendance),
        ], 201);
    }

    /**
     * Self check-out: set check_out_time and calculate shift hours (regular + overtime)
     */
    public function checkOut(Request $request)
    {
        $user = Auth::user();
        $date = Carbon::today()->format('Y-m-d');

        $attendance = Attendance::where('user_id', $user->id)->where('date', $date)->first();

        if (! $attendance) {
            return response()->json([
                'success' => false,
                'message' => 'No check-in found for today. Please check in first.',
            ], 422);
        }

        if ($attendance->check_out_time) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked out today.',
            ], 422);
        }

        $checkOut = Carbon::now();
        $checkIn = Carbon::parse($attendance->check_in_time);
        $breakMinutes = $attendance->break_minutes ?? 0;
        $rawMinutes = $checkOut->diffInMinutes($checkIn);
        // Only subtract break when shift is longer than break (avoid negative for short shifts)
        $totalMinutes = $rawMinutes > $breakMinutes ? $rawMinutes - $breakMinutes : $rawMinutes;
        $totalMinutes = max(0, $totalMinutes);
        $regularMinutes = min(480, $totalMinutes);
        $overtimeMinutes = max(0, $totalMinutes - 480);

        $attendance->update([
            'check_out_time' => $checkOut,
            'total_work_minutes' => $totalMinutes,
            'regular_minutes' => $regularMinutes,
            'overtime_minutes' => $overtimeMinutes,
        ]);
        $attendance->load(['user', 'markedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Check-out recorded. Shift: '.round($regularMinutes / 60, 2).'h regular, '.round($overtimeMinutes / 60, 2).'h overtime.',
            'data' => new AttendanceResource($attendance),
        ]);
    }

    /**
     * Create attendance automatically from daily plan submission
     * This is called when a daily plan is submitted
     */
    public function createFromDailyPlan($userId, $date)
    {
        // Check if attendance already exists
        $existing = Attendance::where('user_id', $userId)
            ->where('date', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Check if it's a holiday
        $holiday = Holiday::where('date', $date)
            ->where('is_active', true)
            ->first();

        if ($holiday) {
            return Attendance::create([
                'user_id' => $userId,
                'date' => $date,
                'status' => 'holiday',
            ]);
        }

        // Create present attendance
        return Attendance::create([
            'user_id' => $userId,
            'date' => $date,
            'status' => 'present',
            'check_in_time' => now(),
        ]);
    }

    /**
     * Get attendance statistics
     */
    public function statistics(Request $request)
    {
        $userId = $request->get('user_id', Auth::id());
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->endOfMonth()->format('Y-m-d'));

        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get();

        // Regular/OT: agar DB me 0 hai lekin check_in/check_out hai to unse nikaalo (0.00h na aaye)
        $totalRegM = 0;
        $totalOtM = 0;
        foreach ($attendances as $att) {
            $regM = $att->regular_minutes ?? 0;
            $otM = $att->overtime_minutes ?? 0;
            if ($regM === 0 && $otM === 0 && $att->check_in_time && $att->check_out_time) {
                $raw = (int) Carbon::parse($att->check_in_time)->diffInMinutes(Carbon::parse($att->check_out_time));
                $regM = min(480, $raw);
                $otM = max(0, $raw - 480);
            }
            $totalRegM += $regM;
            $totalOtM += $otM;
        }

        $stats = [
            'total_days' => $attendances->count(),
            'present' => $attendances->where('status', 'present')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'holiday' => $attendances->where('status', 'holiday')->count(),
            'leave' => $attendances->where('status', 'leave')->count(),
            'half_day' => $attendances->where('status', 'half_day')->count(),
            'total_regular_minutes' => $totalRegM,
            'total_overtime_minutes' => $totalOtM,
            'total_regular_hours' => round($totalRegM / 60, 2),
            'total_overtime_hours' => round($totalOtM / 60, 2),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
