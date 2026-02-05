<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin/TL only - will check in controller
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,holiday,leave,half_day',
            'check_in_time' => 'nullable|date',
            'check_out_time' => 'nullable|date|after:check_in_time',
            'break_minutes' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
