<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConferenceEvent extends Model
{
    protected $fillable = ['name', 'year', 'starts_on', 'ends_on', 'timezone'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ConferenceSession::class);
    }
}
