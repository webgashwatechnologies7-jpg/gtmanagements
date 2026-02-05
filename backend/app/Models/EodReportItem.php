<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EodReportItem extends Model
{
    protected $fillable = [
        'eod_report_id',
        'project_id',
        'task_id',
        'work_summary',
        'progress_percentage',
        'remaining_work',
        'blockers',
        'regular_minutes',
        'overtime_minutes',
        'status_update',
    ];

    protected $casts = [
        'progress_percentage' => 'integer',
        'regular_minutes' => 'integer',
        'overtime_minutes' => 'integer',
    ];

    /**
     * Get the EOD report
     */
    public function eodReport(): BelongsTo
    {
        return $this->belongsTo(EodReport::class);
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
     * Note: Task model will be created in future phase
     */
    // public function task(): BelongsTo
    // {
    //     return $this->belongsTo(Task::class);
    // }
}
