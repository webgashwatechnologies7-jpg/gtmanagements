<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
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
            'team_lead_id' => 'nullable|exists:users,id',
            'project_manager_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:active,inactive',
            'members' => 'nullable|array',
            'members.*' => 'exists:users,id',
        ];
    }
}
