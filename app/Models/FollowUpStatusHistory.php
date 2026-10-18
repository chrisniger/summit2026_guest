<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'follow_up_status_history';

    protected $fillable = ['guest_record_id', 'from_status', 'to_status', 'changed_by_user_id', 'changed_at_utc', 'reason', 'activity_id'];

    protected function casts(): array
    {
        return ['changed_at_utc' => 'datetime'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(GuestRecord::class, 'guest_record_id');
    }
}
