<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin only - will check in controller
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof \App\Models\User ? $user->id : $user;

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'password' => ['sometimes', 'nullable', 'string', Password::min(8)],
            'employee_id' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'employee_id')->ignore($userId)
            ],
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
