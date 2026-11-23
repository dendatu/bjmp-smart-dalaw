<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedDate extends Model
{
    protected $fillable = [
        'facility_id',
        'blocked_by',
        'blocked_date',
        'reason',
    ];

    protected $casts = [
        'blocked_date' => 'date',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Scope to only blocked dates visible to the given user.
     * Super admins see all; facility admins see only their facility's.
     */
    public function scopeVisibleToUser(Builder $query, ?User $user = null): Builder
    {
        if ($user === null || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('facility_id', $user->facility_id ?? 0);
    }
}
