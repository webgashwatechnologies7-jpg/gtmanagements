<?php

namespace App\Http\Requests\ProjectType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class UpdateProjectTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin only - will check in controller
    }

    public function rules(): array
    {
        $projectType = $this->route('project_type');
        $projectTypeId = $projectType instanceof \App\Models\ProjectType ? $projectType->id : $projectType;

        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('project_types', 'slug')->ignore($projectTypeId)
            ],
            'description' => 'nullable|string',
            'fields' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && !$this->has('slug')) {
            $this->merge([
                'slug' => Str::slug($this->name),
            ]);
        }
    }
}
