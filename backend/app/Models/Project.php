<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'custom_fields',
        'project_type_id',
        'priority',
        'status',
        'deadline',
        'estimated_hours',
        'actual_hours',
        'project_manager_id',
        'team_id',
        'created_by',
        'start_date',
        'completion_date',
    ];

    protected $casts = [
        'deadline' => 'date',
        'start_date' => 'date',
        'completion_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'custom_fields' => 'array',
    ];

    /**
     * Get the project type
     */
    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    /**
     * Get the project manager
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /**
     * Get the team
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the project
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get project assignments
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    /**
     * Latest active Team Lead assignment for this project.
     */
    public function latestTeamLeadAssignment(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class)
            ->where('assignment_level', 'tl')
            ->where('status', 'active')
            ->orderByDesc('assigned_at')
            ->orderByDesc('id');
    }

    /**
     * Get assigned users through assignments
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_assignments', 'project_id', 'assigned_to_user_id')
            ->withPivot('assigned_by_user_id', 'assignment_level', 'assigned_at', 'status')
            ->withTimestamps();
    }

    /**
     * Get tasks for this project
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
