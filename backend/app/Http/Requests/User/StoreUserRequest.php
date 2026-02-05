<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin only - will check in controller
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', Password::min(8)],
            'employee_id' => 'required|string|max:50|unique:users',
            'phone' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive,suspended',
            'overtime_allowed' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
            'team_id' => 'nullable|exists:teams,id',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'exists:teams,id',
            'project_manager_id' => 'nullable|exists:users,id',
        ];
    }
}
