<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConferenceSession extends Model
{
    protected $fillable = ['conference_event_id', 'code', 'day_number', 'date_local', 'name', 'label', 'starts_at_utc', 'ends_at_utc', 'enabled', 'attendance_locked_at'];

    protected function casts(): array
    {
        return ['date_local' => 'date', 'starts_at_utc' => 'immutable_datetime', 'ends_at_utc' => 'immutable_datetime', 'enabled' => 'boolean', 'attendance_locked_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ConferenceEvent::class, 'conference_event_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(InfoAttendanceRecord::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(GuestRecord::class);
    }

    public function scopeCurrentConference(Builder $query): Builder
    {
        return $query->whereHas('event', fn (Builder $event) => $event->where('year', 2026));
    }
}
