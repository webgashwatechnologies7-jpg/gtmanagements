<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyPlan extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'submitted_at' => 'datetime',
    ];

    /**
     * Get the user who created the plan
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plan items
     */
    public function items(): HasMany
    {
        return $this->hasMany(DailyPlanItem::class);
    }
}
