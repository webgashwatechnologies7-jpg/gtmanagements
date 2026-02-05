<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'employee_id' => $this->employee_id,
            'phone' => $this->phone,
            'status' => $this->status,
            'overtime_allowed' => $this->overtime_allowed,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ];
                });
            }),
            'teams' => $this->whenLoaded('teams', function () {
                return $this->teams->map(function ($team) {
                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'pivot' => [
                            'role_in_team' => $team->pivot->role_in_team ?? null,
                            'status' => $team->pivot->status ?? null,
                        ],
                    ];
                });
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
