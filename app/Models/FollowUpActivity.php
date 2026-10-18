<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpActivity extends Model
{
    protected $fillable = ['guest_record_id', 'assignment_id', 'actor_user_id', 'method', 'attempted_at_utc', 'outcome', 'comment', 'previous_status', 'new_status'];

    protected function casts(): array
    {
        return ['attempted_at_utc' => 'datetime'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(GuestRecord::class, 'guest_record_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(FollowUpAssignment::class, 'assignment_id');
    }
}
