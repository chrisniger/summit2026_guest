<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfoAttendanceRecord extends Model
{
    protected $fillable = ['info_attendee_id', 'conference_session_id', 'checked_in_at_utc', 'source', 'marked_by_user_id', 'registration_event_id', 'import_row_id', 'voided_at', 'void_reason'];

    protected function casts(): array
    {
        return ['checked_in_at_utc' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(InfoAttendee::class, 'info_attendee_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConferenceSession::class, 'conference_session_id');
    }
}
