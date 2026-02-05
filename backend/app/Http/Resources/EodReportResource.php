<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EodReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'date' => $this->date?->format('Y-m-d'),
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'rejection_reason' => $this->rejection_reason,
            'user' => $this->whenLoaded('user', function () {
                $teams = $this->user->relationLoaded('teams')
                    ? $this->user->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->all()
                    : [];
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'teams' => $teams,
                ];
            }),
            'approver' => $this->whenLoaded('approver', function () {
                return [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                    'email' => $this->approver->email,
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
                        'work_summary' => $item->work_summary,
                        'progress_percentage' => $item->progress_percentage,
                        'remaining_work' => $item->remaining_work,
                        'blockers' => $item->blockers,
                        'regular_minutes' => $item->regular_minutes,
                        'overtime_minutes' => $item->overtime_minutes,
                        'status_update' => $item->status_update,
                    ];
                });
            }),
            'approvals' => $this->whenLoaded('approvals', function () {
                return $this->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'approver' => $approval->approver ? [
                            'id' => $approval->approver->id,
                            'name' => $approval->approver->name,
                        ] : null,
                        'status' => $approval->status,
                        'comment' => $approval->comment,
                        'created_at' => $approval->created_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
