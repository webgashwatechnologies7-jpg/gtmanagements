<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'approver_id',
        'status',
        'comment',
    ];

    /**
     * Get the approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Get the entity being approved (polymorphic)
     */
    public function entity()
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }
}
