<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlanItem extends Model
{
    protected $fillable = [
        'daily_plan_id',
        'project_id',
        'task_id',
        'planned_hours',
        'description',
        'priority',
    ];

    protected $casts = [
        'planned_hours' => 'decimal:2',
    ];

    /**
     * Get the daily plan
     */
    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the task (if applicable)
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
