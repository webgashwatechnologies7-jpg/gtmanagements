<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Holiday\StoreHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    /**
     * Display a listing of holidays
     */
    public function index(Request $request)
    {
        $query = Holiday::with('creator');

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $holidays = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => HolidayResource::collection($holidays->items()),
            'meta' => [
                'current_page' => $holidays->currentPage(),
                'last_page' => $holidays->lastPage(),
                'per_page' => $holidays->perPage(),
                'total' => $holidays->total(),
            ],
        ]);
    }

    /**
     * Store a newly created holiday
     */
    public function store(StoreHolidayRequest $request)
    {
        $holidayData = $request->validated();
        $holidayData['created_by'] = Auth::id();
        $holidayData['type'] = $holidayData['type'] ?? 'holiday';
        $holidayData['is_active'] = $holidayData['is_active'] ?? true;

        $holiday = Holiday::create($holidayData);
        $holiday->load('creator');

        return response()->json([
            'success' => true,
            'message' => 'Holiday created successfully',
            'data' => new HolidayResource($holiday),
        ], 201);
    }

    /**
     * Display the specified holiday
     */
    public function show(Holiday $holiday)
    {
        $holiday->load('creator');

        return response()->json([
            'success' => true,
            'data' => new HolidayResource($holiday),
        ]);
    }

    /**
     * Update the specified holiday
     */
    public function update(Request $request, Holiday $holiday)
    {
        $request->validate([
            'date' => [
                'sometimes',
                'required',
                'date',
                Rule::unique('holidays', 'date')->ignore($holiday->id)
            ],
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|in:holiday,weekend,custom',
            'is_active' => 'sometimes|boolean',
        ]);

        $holiday->update($request->validated());
        $holiday->load('creator');

        return response()->json([
            'success' => true,
            'message' => 'Holiday updated successfully',
            'data' => new HolidayResource($holiday),
        ]);
    }

    /**
     * Remove the specified holiday
     */
    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Holiday deleted successfully',
        ]);
    }
}
