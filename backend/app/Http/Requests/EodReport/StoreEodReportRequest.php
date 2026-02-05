<?php

namespace App\Http\Requests\EodReport;

use Illuminate\Foundation\Http\FormRequest;

class StoreEodReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Employee only - will check in controller
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.project_id' => 'required|exists:projects,id',
            'items.*.task_id' => 'nullable|exists:tasks,id',
            'items.*.work_summary' => 'required|string',
            'items.*.progress_percentage' => 'nullable|integer|min:0|max:100',
            'items.*.remaining_work' => 'nullable|string',
            'items.*.blockers' => 'nullable|string',
            'items.*.regular_minutes' => 'nullable|integer|min:0',
            'items.*.overtime_minutes' => 'nullable|integer|min:0',
            'items.*.status_update' => 'nullable|string|max:50',
        ];
    }
}
