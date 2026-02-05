<?php

namespace App\Http\Requests\DailyPlan;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyPlanRequest extends FormRequest
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
            'items.*.task_name' => 'nullable|string|max:200',
            'items.*.planned_hours' => 'required|numeric|min:0.1|max:8',
            'items.*.description' => 'required|string|min:1',
            'items.*.priority' => 'nullable|in:high,medium,low',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $total = array_reduce($items, fn($sum, $i) => $sum + (float)($i['planned_hours'] ?? 0), 0);
            if ($total > 8.01) {
                $validator->errors()->add('items', 'Plan total cannot exceed 8 hours. Current total: ' . round($total, 1) . ' hours.');
            }
            foreach ($items as $idx => $item) {
                if (empty($item['task_id']) && strlen(trim($item['description'] ?? '')) < 20) {
                    $validator->errors()->add("items.{$idx}.description", 'Describe in detail what needs to be done for the new task (at least 20 characters).');
                }
            }
        });
    }
}
