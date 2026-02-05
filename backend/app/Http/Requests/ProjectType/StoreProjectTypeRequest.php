<?php

namespace App\Http\Requests\ProjectType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreProjectTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin only - will check in controller
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:project_types,slug',
            'description' => 'nullable|string',
            'fields' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('slug') && $this->filled('name')) {
            $this->merge([
                'slug' => Str::slug($this->name),
            ]);
        }
    }
}
