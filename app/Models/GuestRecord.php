<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GuestRecord extends Model
{
    use HasFactory;

    protected $fillable = ['conference_session_id', 'full_name', 'first_name', 'last_name', 'phone_raw', 'phone_normalized', 'gender', 'home_address', 'heard_about', 'captured_at_original_utc', 'entry_notes', 'created_by_user_id', 'import_row_id', 'legacy_officer_name', 'raw_source_json'];

    protected $attributes = ['current_status' => 'NOT CONTACTED'];

    protected function casts(): array
    {
        return ['captured_at_original_utc' => 'datetime', 'raw_source_json' => 'array', 'archived_at' => 'datetime', 'version' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConferenceSession::class, 'conference_session_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FollowUpAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(FollowUpAssignment::class)->whereNull('unassigned_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(FollowUpActivity::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(FollowUpStatusHistory::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('guest.access')) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasRole('super_admin') || $user->hasRole('follow_up_admin')) {
            return $query;
        }
        if ($user->hasRole('data_entry')) {
            return $query->where('created_by_user_id', $user->id);
        }

        return $query->whereHas('activeAssignment', fn (Builder $assignment) => $assignment->where('officer_user_id', $user->id));
    }
}
