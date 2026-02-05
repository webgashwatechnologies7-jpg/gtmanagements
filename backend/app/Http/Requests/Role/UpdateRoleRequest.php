<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin only - will check in controller
    }

    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role instanceof \App\Models\Role ? $role->id : $role;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('roles', 'name')->ignore($roleId)
            ],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('roles', 'slug')->ignore($roleId)
            ],
            'status' => 'nullable|in:active,inactive',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ];
    }
}
