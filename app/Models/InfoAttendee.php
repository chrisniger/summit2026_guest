<?php

namespace App\Models;

use App\Services\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InfoAttendee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['first_name', 'last_name', 'phone_raw', 'phone_normalized', 'email', 'member', 'summit_centre', 'commits_to_invite', 'invite_count', 'location', 'abuja_area', 'first_time', 'bus_shuttle', 'bus_route', 'attendance_preference', 'heard_about', 'feedback', 'consent', 'registration_original_at_utc', 'registration_source', 'source_external_id', 'raw_source_json', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['member' => 'boolean', 'first_time' => 'boolean', 'consent' => 'boolean', 'raw_source_json' => 'array', 'registration_original_at_utc' => 'datetime'];
    }

    public function registrationEvents(): HasMany
    {
        return $this->hasMany(InfoRegistrationEvent::class);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }
        $literal = addcslashes($search, '\\%_');

        return $query->where(function (Builder $match) use ($search, $literal) {
            $match->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$literal.'%']);
            if (preg_match('/^[+0-9\\s().-]+$/', $search)) {
                $match->orWhere('phone_normalized', 'like', PhoneNumber::searchPrefix($search).'%');
            }
        });
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(InfoAttendanceRecord::class);
    }
}
