<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'date' => $this->date?->format('Y-m-d'),
            'status' => $this->status,
            // ISO 8601 with timezone so frontend shows correct local time (server stores UTC)
            'check_in_time' => $this->check_in_time?->utc()->toIso8601String(),
            'check_out_time' => $this->check_out_time?->utc()->toIso8601String(),
            'break_minutes' => $this->break_minutes,
            'total_work_minutes' => $this->total_work_minutes,
            'regular_minutes' => $this->regular_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'marked_by' => $this->whenLoaded('markedBy', function () {
                return [
                    'id' => $this->markedBy->id,
                    'name' => $this->markedBy->name,
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
