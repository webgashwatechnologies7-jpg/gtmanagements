<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectType extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'fields',
        'is_active',
    ];

    protected $casts = [
        'fields' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get projects of this type
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
