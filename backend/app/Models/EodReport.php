<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EodReport extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'date',
        'status',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the user who created the report
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the report items
     */
    public function items(): HasMany
    {
        return $this->hasMany(EodReportItem::class);
    }

    /**
     * Get approvals for this report
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'entity_id')
            ->where('entity_type', 'eod_report');
    }
}
