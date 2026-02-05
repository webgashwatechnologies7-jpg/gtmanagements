<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'team_lead_id',
        'project_manager_id',
        'status',
    ];

    /**
     * Get the team lead user
     */
    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    /**
     * Get the project manager user
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /**
     * Get the team members
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role_in_team', 'joined_at', 'status')
            ->withTimestamps();
    }

    /**
     * Get active team members
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    /**
     * Get team members count
     */
    public function getMembersCountAttribute()
    {
        return $this->activeMembers()->count();
    }
}
