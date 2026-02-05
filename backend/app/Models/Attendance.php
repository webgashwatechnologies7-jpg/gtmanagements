<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'user_id',
        'date',
        'status',
        'check_in_time',
        'check_out_time',
        'break_minutes',
        'total_work_minutes',
        'regular_minutes',
        'overtime_minutes',
        'marked_by',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'break_minutes' => 'integer',
        'total_work_minutes' => 'integer',
        'regular_minutes' => 'integer',
        'overtime_minutes' => 'integer',
    ];

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who marked this attendance
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
