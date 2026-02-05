<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin/PM only - will check in controller
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'project_type_id' => 'nullable|exists:project_types,id',
            'priority' => 'nullable|in:high,medium,low',
            'status' => 'nullable|in:planning,active,on_hold,completed,cancelled',
            'deadline' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
            'project_manager_id' => 'nullable|exists:users,id',
            'team_id' => 'nullable|exists:teams,id',
            'start_date' => 'nullable|date',
            'completion_date' => 'nullable|date',
        ];
    }
}
