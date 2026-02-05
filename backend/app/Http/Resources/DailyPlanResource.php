<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'date' => $this->date?->format('Y-m-d'),
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'project_id' => $item->project_id,
                        'project' => $item->project ? [
                            'id' => $item->project->id,
                            'name' => $item->project->name,
                        ] : null,
                        'task_id' => $item->task_id,
                        'task' => $item->task ? ['id' => $item->task->id, 'name' => $item->task->name] : null,
                        'planned_hours' => $item->planned_hours,
                        'description' => $item->description,
                        'priority' => $item->priority,
                    ];
                });
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
