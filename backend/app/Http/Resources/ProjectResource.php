<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $teamLead = null;
        if ($this->relationLoaded('latestTeamLeadAssignment')) {
            $assignment = $this->latestTeamLeadAssignment->first();
            $user = $assignment?->relationLoaded('assignedTo') ? $assignment?->assignedTo : null;
            if ($user) {
                $teamLead = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'custom_fields' => $this->custom_fields,
            'priority' => $this->priority,
            'status' => $this->status,
            'deadline' => $this->deadline?->format('Y-m-d'),
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'completion_date' => $this->completion_date?->format('Y-m-d'),
            'project_type' => $this->whenLoaded('projectType', function () {
                return [
                    'id' => $this->projectType->id,
                    'name' => $this->projectType->name,
                    'slug' => $this->projectType->slug,
                ];
            }),
            'project_manager' => $this->whenLoaded('projectManager', function () {
                return [
                    'id' => $this->projectManager->id,
                    'name' => $this->projectManager->name,
                    'email' => $this->projectManager->email,
                ];
            }),
            'team' => $this->whenLoaded('team', function () {
                return [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ];
            }),
            'team_lead' => $teamLead,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'assigned_users' => $this->whenLoaded('assignedUsers', function () {
                return $this->assignedUsers->map(function ($user) {
                    $assignedAt = $user->pivot->assigned_at ?? null;
                    $assignedAtFormatted = null;
                    if ($assignedAt) {
                        try {
                            $assignedAtFormatted = $assignedAt instanceof \DateTimeInterface
                                ? Carbon::instance($assignedAt)->format('Y-m-d H:i:s')
                                : Carbon::parse($assignedAt)->format('Y-m-d H:i:s');
                        } catch (\Throwable $e) {
                            $assignedAtFormatted = (string) $assignedAt;
                        }
                    }
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'assignment_level' => $user->pivot->assignment_level,
                        'assigned_at' => $assignedAtFormatted,
                        'status' => $user->pivot->status,
                    ];
                });
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
