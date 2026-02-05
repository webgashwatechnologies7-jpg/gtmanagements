<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'assigned_to_user_id',
        'priority',
        'status',
        'estimated_hours',
        'actual_hours',
        'deadline',
        'completion_notes',
        'created_by',
    ];

    protected $casts = [
        'deadline' => 'date',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
    ];

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the assigned user
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get the user who created the task
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get time entries for this task
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Get daily plan items for this task
     */
    public function dailyPlanItems(): HasMany
    {
        return $this->hasMany(DailyPlanItem::class);
    }

    /**
     * Get EOD report items for this task
     */
    public function eodReportItems(): HasMany
    {
        return $this->hasMany(EodReportItem::class);
    }
}
