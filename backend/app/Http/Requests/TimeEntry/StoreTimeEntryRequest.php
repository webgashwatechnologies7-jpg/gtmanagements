<?php

namespace App\Http\Requests\TimeEntry;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Employee only - will check in controller
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'nullable', // Tasks table will be created in future phase
            'minutes' => 'required|integer|min:1',
            'entry_type' => 'nullable|in:regular,overtime',
            'description' => 'nullable|string',
        ];
    }
}
