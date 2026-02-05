<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'priority' => ['required', Rule::in(['high', 'medium', 'low'])],
            'status' => ['nullable', Rule::in(['todo', 'in_progress', 'review', 'completed', 'cancelled'])],
            'estimated_hours' => 'nullable|numeric|min:0|max:9999.99',
            'deadline' => 'nullable|date',
        ];
    }
}
