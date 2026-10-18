<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpAssignment extends Model
{
    protected $fillable = ['guest_record_id', 'officer_user_id', 'assigned_by_user_id', 'assigned_at', 'unassigned_at', 'reason'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'unassigned_at' => 'datetime'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(GuestRecord::class, 'guest_record_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_user_id');
    }
}
