<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfoRegistrationEvent extends Model
{
    protected $fillable = ['info_attendee_id', 'source', 'registered_at_utc', 'import_row_id', 'created_by_user_id', 'source_fingerprint'];

    protected function casts(): array
    {
        return ['registered_at_utc' => 'datetime'];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(InfoAttendee::class, 'info_attendee_id');
    }
}
