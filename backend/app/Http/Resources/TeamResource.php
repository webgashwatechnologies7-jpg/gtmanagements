<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'team_lead' => $this->whenLoaded('teamLead', function () {
                return [
                    'id' => $this->teamLead->id,
                    'name' => $this->teamLead->name,
                    'email' => $this->teamLead->email,
                ];
            }),
            'project_manager' => $this->whenLoaded('projectManager', function () {
                return [
                    'id' => $this->projectManager->id,
                    'name' => $this->projectManager->name,
                    'email' => $this->projectManager->email,
                ];
            }),
            'members' => $this->whenLoaded('members', function () {
                return $this->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'employee_id' => $member->employee_id,
                        'role_in_team' => $member->pivot->role_in_team,
                        'joined_at' => $member->pivot->joined_at,
                        'status' => $member->pivot->status,
                    ];
                });
            }),
            /** All Team Leads of this department (for Employee dropdown) */
            'team_leads' => $this->computeTeamLeadsArray(),
            'members_count' => $this->when(isset($this->members_count), $this->members_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * All Team Leads for this department (official + members with TL role).
     */
    protected function computeTeamLeadsArray(): array
    {
        $tlSlugs = ['team_lead', 'team_leader'];
        $list = [];
        if ($this->relationLoaded('teamLead') && $this->teamLead) {
            $list[] = ['id' => $this->teamLead->id, 'name' => $this->teamLead->name, 'email' => $this->teamLead->email];
        }
        if ($this->relationLoaded('members')) {
            $fromMembers = $this->members->filter(function ($m) use ($tlSlugs) {
                return $m->relationLoaded('roles') && $m->roles->whereIn('slug', $tlSlugs)->isNotEmpty();
            })->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email])->values()->all();
            $ids = array_column($list, 'id');
            foreach ($fromMembers as $tl) {
                if (!in_array($tl['id'], $ids)) {
                    $list[] = $tl;
                    $ids[] = $tl['id'];
                }
            }
        }
        return array_values($list);
    }
}
