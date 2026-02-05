<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin only - will check in controller
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50|unique:roles',
            'slug' => 'required|string|max:50|unique:roles',
            'status' => 'nullable|in:active,inactive',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ];
    }
}
