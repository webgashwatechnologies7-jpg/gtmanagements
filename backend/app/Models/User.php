<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'phone',
        'status',
        'overtime_allowed',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'overtime_allowed' => 'boolean',
        ];
    }

    /**
     * Get the roles for the user.
     */
    public function roles()
    {
        return $this->belongsToMany(\App\Models\Role::class, 'user_roles');
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole($role)
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles)
    {
        return $this->roles()->whereIn('slug', $roles)->exists();
    }

    /**
     * Get all permissions for the user.
     * Optimized to avoid N+1 queries.
     */
    public function permissions()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }

    /**
     * Check if user has a specific permission.
     * Optimized to avoid loading all permissions.
     */
    public function hasPermission($permission)
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('slug', $permission);
            })
            ->exists();
    }

    /**
     * Get the teams this user belongs to
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role_in_team', 'joined_at', 'status')
            ->withTimestamps();
    }

    /**
     * Get teams where user is team lead
     */
    public function teamsAsLead()
    {
        return $this->hasMany(Team::class, 'team_lead_id');
    }

    /**
     * Get teams where user is project manager
     */
    public function teamsAsPM()
    {
        return $this->hasMany(Team::class, 'project_manager_id');
    }
}
