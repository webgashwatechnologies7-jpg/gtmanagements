<?php

namespace App\Http\Requests\Holiday;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin only - will check in controller
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date|unique:holidays,date',
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:holiday,weekend,custom',
            'is_active' => 'nullable|boolean',
        ];
    }
}
